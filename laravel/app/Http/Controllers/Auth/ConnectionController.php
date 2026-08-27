<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\AccountLinkRejected;
use App\Http\Controllers\Concerns\ConfirmsPassword;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\DiscordAccountLinker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

/**
 * Connecting/disconnecting a Discord account, gated behind the current
 * password. Lives in Auth\ next to PasswordController because both serve
 * /account/* and both are, in effect, credential management: once "log in
 * with Discord" ships, a connected account is a second way into an account
 * that has no email and therefore no recovery path.
 */
class ConnectionController extends Controller
{
    use ConfirmsPassword;

    /**
     * How long the user has to finish the Discord round-trip after confirming
     * their password. Short on purpose — the marker is what ties the callback
     * back to that confirmation.
     */
    private const INTENT_TTL_MINUTES = 5;

    private const INTENT_KEY = 'discord_link_intent';

    public function edit(Request $request): View
    {
        return view('account.connections', [
            'discordAccount' => $request->user()->discordAccount,
            'hasPassword' => $request->user()->hasUsablePassword(),
            'integrationEnabled' => SiteSetting::discordIntegrationEnabled(),
        ]);
    }

    public function redirectToDiscord(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'string']]);

        if (! $this->passwordConfirmed($request)) {
            return back()->with('error', 'Incorrect password.');
        }

        $request->session()->put(self::INTENT_KEY, [
            'user_iduser' => $request->user()->iduser,
            'expires_at' => now()->addMinutes(self::INTENT_TTL_MINUTES)->timestamp,
        ]);

        return Socialite::driver('discord')
            // setScopes REPLACES (scopes() would merge). `email` is not
            // stored anywhere — it is requested because Discord only returns
            // the `verified` flag we gate on when that scope is granted.
            ->setScopes(['identify', 'email'])
            // Without this the provider forces `prompt=none`, which silently
            // authorizes whatever Discord session happens to be live in the
            // browser. withConsent() suppresses that so the user actually
            // sees which account they're about to connect, and can switch.
            ->withConsent()
            ->with(['prompt' => 'consent'])
            ->redirect();
    }

    public function discordCallback(Request $request, DiscordAccountLinker $linker): RedirectResponse
    {
        if (! $this->consumeIntent($request)) {
            return redirect()->route('connections.edit')
                ->with('error', 'That Discord connection request expired or was invalid. Please try again.');
        }

        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (\Throwable $e) {
            // Covers InvalidStateException and anything Discord or the
            // provider's user-mapping throws. Reported, never shown — the
            // message can carry request details we don't want on the page.
            report($e);

            return redirect()->route('connections.edit')
                ->with('error', "Couldn't complete the Discord connection. Please try again.");
        }

        // Fail closed: absent or non-true both count as unverified, so a
        // change in Discord's payload shape can't quietly open the gate.
        $verified = ($discordUser->getRaw()['verified'] ?? false) === true;

        try {
            $linker->link(
                $request->user(),
                (string) $discordUser->getId(),
                $discordUser->getNickname() ?: $discordUser->getName(),
                $verified,
            );
        } catch (AccountLinkRejected $e) {
            return redirect()->route('connections.edit')->with('error', $e->getMessage());
        } finally {
            // Runs on the rejection paths too: we asked for the token, we're
            // done with it either way, and it is never persisted.
            $linker->revokeToken($discordUser->token ?? null);
        }

        return redirect()->route('connections.edit')->with('status', 'Discord account connected.');
    }

    public function destroyDiscord(Request $request, DiscordAccountLinker $linker): RedirectResponse
    {
        // Explicit, not incidental. passwordConfirmed() would already refuse a
        // passwordless account, but silently and with a misleading "Incorrect
        // password." Disconnecting the only way into an account that has no
        // email and no password reset would strand the user for good.
        if (! $request->user()->hasUsablePassword()) {
            return back()->with('error', 'Set a password first — Discord is currently the only way into your account.');
        }

        $request->validate(['current_password' => ['required', 'string']]);

        if (! $this->passwordConfirmed($request)) {
            return back()->with('error', 'Incorrect password.');
        }

        $linker->unlink($request->user());

        return redirect()->route('connections.edit')->with('status', 'Discord account disconnected.');
    }

    /**
     * Pulls the single-use marker written before the redirect and checks it
     * still belongs to whoever is signed in now. Socialite's `state` check
     * proves the callback came from our redirect; this proves that redirect
     * was password-confirmed by *this* user, and that it hasn't been sitting
     * around in a session for hours.
     */
    private function consumeIntent(Request $request): bool
    {
        $intent = $request->session()->pull(self::INTENT_KEY);

        // Compared as strings: user.iduser is a native int on SQLite (tests)
        // but PDO hands back MySQL's BIGINT UNSIGNED as a string, and the
        // marker survives a round-trip through session serialization in
        // between — see the typing note in CLAUDE.md.
        return is_array($intent)
            && (string) ($intent['user_iduser'] ?? '') === (string) $request->user()->iduser
            && (int) ($intent['expires_at'] ?? 0) >= now()->timestamp;
    }
}
