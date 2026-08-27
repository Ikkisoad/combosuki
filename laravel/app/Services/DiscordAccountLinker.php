<?php

namespace App\Services;

use App\Exceptions\AccountLinkRejected;
use App\Models\User;
use App\Models\UserConnectedAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Attaches a Discord identity to a Combo好き account.
 *
 * Because `user` has no email column and there is no password-reset flow, a
 * linked Discord account is effectively a second credential for an account
 * with no recovery path — so this refuses far more than it accepts:
 * unverified Discord emails, snowflakes that don't look like snowflakes,
 * accounts already claimed by someone else, and silent overwrites of an
 * existing link.
 *
 * Nothing sensitive is persisted. The caller reads the Discord profile once
 * and passes in only the snowflake, the display name and the verified flag;
 * the access token and email address never reach this class's writes.
 */
class DiscordAccountLinker
{
    public const PROVIDER = 'discord';

    /**
     * Discord snowflakes are decimal strings; 20 digits leaves headroom over
     * the 19 a 64-bit id actually needs. Validated because this value comes
     * from outside and lands in a unique index.
     */
    private const MAX_SNOWFLAKE_LENGTH = 20;

    /** Matches provider_nickname's column width. */
    private const MAX_NICKNAME_LENGTH = 190;

    /**
     * @throws AccountLinkRejected
     */
    public function link(User $user, string $discordId, ?string $nickname, bool $emailVerified): UserConnectedAccount
    {
        if (! $emailVerified) {
            $this->reject($user, $discordId, 'email_not_verified',
                "Your Discord account's email address must be verified before you can connect it.");
        }

        if ($discordId === '' || ! ctype_digit($discordId) || strlen($discordId) > self::MAX_SNOWFLAKE_LENGTH) {
            $this->reject($user, $discordId, 'malformed_snowflake',
                "That doesn't look like a valid Discord account. Please try again.");
        }

        $existing = UserConnectedAccount::where('provider', self::PROVIDER)
            ->where('provider_user_id', $discordId)
            ->first();

        if ($existing) {
            // Never name the other account — that would turn this endpoint
            // into a "which Combo好き user owns this Discord id?" oracle.
            $this->reject($user, $discordId, 'claimed_by_other_account',
                $existing->user_iduser === $user->iduser
                    ? 'That Discord account is already connected to your account.'
                    : 'That Discord account is already connected to another Combo好き account.');
        }

        if ($user->discordAccount()->exists()) {
            $this->reject($user, $discordId, 'user_already_linked',
                'You already have a Discord account connected. Disconnect it first.');
        }

        try {
            $connection = UserConnectedAccount::create([
                'provider' => self::PROVIDER,
                'provider_user_id' => $discordId,
                // Truncated rather than trusted: Discord caps usernames well
                // under this, but MySQL runs STRICT_TRANS_TABLES, so an
                // over-long value would be a hard insert error — and SQLite
                // (tests) ignores column widths, so it would never show up
                // until production. See the typing note in CLAUDE.md.
                'provider_nickname' => $nickname === null
                    ? null
                    : mb_substr($nickname, 0, self::MAX_NICKNAME_LENGTH),
                'user_iduser' => $user->iduser,
            ]);
        } catch (QueryException $e) {
            // Narrow on purpose. Only an integrity-constraint violation (SQLSTATE
            // 23000) means "lost a race against a concurrent callback, the unique
            // index did its job". Catching every QueryException here would report
            // unrelated database failures as "already connected to another
            // account" — a message that is both wrong and quietly misleading,
            // since it is also how a caller learns a Discord id is taken.
            if (($e->getCode() !== '23000')) {
                throw $e;
            }

            report($e);

            // Two different unique indexes can raise 23000 here — provider_user_id
            // (someone else's callback beat this one) and user_iduser+provider
            // (this same user's own concurrent request, e.g. a double-submit).
            // The pre-write checks above already ran as separate reads before
            // this write, so re-check post-race rather than assuming it was
            // always "someone else" — that message would be simply wrong for
            // the second case.
            if ($user->discordAccount()->exists()) {
                $this->reject($user, $discordId, 'unique_violation',
                    'That Discord account is already connected to your account.');
            }

            $this->reject($user, $discordId, 'unique_violation',
                'That Discord account is already connected to another Combo好き account.');
        }

        Log::info('Discord account connected.', [
            'user_iduser' => $user->iduser,
            'discord_user_id' => $discordId,
        ]);

        return $connection;
    }

    public function unlink(User $user): void
    {
        $deleted = $user->discordAccount()->delete();

        if ($deleted) {
            Log::info('Discord account disconnected.', ['user_iduser' => $user->iduser]);
        }
    }

    /**
     * Hands the access token straight back to Discord once the profile has
     * been read, so the grant doesn't sit around usable — we have no further
     * need for it and store it nowhere.
     *
     * Best-effort by design: a failure here means a token expires on its own
     * schedule instead of immediately, which is not worth failing an
     * otherwise-successful link over. Same treatment outbound Discord calls
     * already get in DiscordInteractionController::syncPrivateCombleFollowUp.
     */
    public function revokeToken(?string $token): void
    {
        $clientId = config('services.discord.client_id');
        $clientSecret = config('services.discord.client_secret');

        if (! $token || ! $clientId || ! $clientSecret) {
            return;
        }

        try {
            Http::asForm()->timeout(5)->post('https://discord.com/api/v10/oauth2/token/revoke', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'token' => $token,
                'token_type_hint' => 'access_token',
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @throws AccountLinkRejected
     */
    private function reject(User $user, string $discordId, string $reason, string $message): never
    {
        // Worth a log line rather than a silent bounce: a burst of
        // "claimed_by_other_account" is what an attempted takeover, or a user
        // confused about which account they're signed into, looks like.
        Log::warning('Discord account link rejected.', [
            'user_iduser' => $user->iduser,
            'discord_user_id' => $discordId,
            'reason' => $reason,
        ]);

        throw new AccountLinkRejected($message);
    }
}
