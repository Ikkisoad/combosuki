<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\AccountLinkRejected;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserConnectedAccount;
use App\Services\DiscordAccountLinker;
use App\Services\NicknamePolicy;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

/**
 * Signing in and signing up with Discord.
 *
 * Kept separate from ConnectionController (which links Discord to an account
 * that is already signed in) because the two flows have opposite
 * authentication requirements: this one is guest-only, that one is auth-only.
 * They also use different callback URLs so neither endpoint has to decide at
 * runtime which kind of request it is holding — that decision is where auth
 * bugs live.
 *
 * An account created here has exactly one credential and this app has no
 * email and no password reset, so the surrounding guards matter:
 * AuthController refuses password login for a passwordless account, and
 * ConnectionController refuses to unlink while Discord is the only way in.
 */
class DiscordAuthController extends Controller
{
    private const INTENT_KEY = 'discord_auth_intent';

    private const IDENTITY_KEY = 'discord_registration_identity';

    private const INTENT_TTL_MINUTES = 5;

    /**
     * Longer than the OAuth hop itself: this one has to survive the user
     * sitting on the "choose your nickname" screen.
     */
    private const IDENTITY_TTL_MINUTES = 15;

    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->put(self::INTENT_KEY, [
            'expires_at' => now()->addMinutes(self::INTENT_TTL_MINUTES)->timestamp,
        ]);

        return Socialite::driver('discord')
            // `email` is never stored; it is requested because Discord only
            // returns the `verified` flag we gate on when that scope is granted.
            ->setScopes(['identify', 'email'])
            // Without this the provider forces prompt=none, which silently
            // authorizes whatever Discord session is open in the browser —
            // for a *login* button that would mean signing someone into an
            // account they never chose.
            ->withConsent()
            ->with(['prompt' => 'consent'])
            ->redirectUrl($this->callbackUrl())
            ->redirect();
    }

    public function callback(Request $request, DiscordAccountLinker $linker): RedirectResponse
    {
        if (! $this->consumeIntent($request)) {
            return redirect()->route('login')
                ->with('error', 'That Discord sign-in request expired or was invalid. Please try again.');
        }

        try {
            $discordUser = Socialite::driver('discord')
                ->redirectUrl($this->callbackUrl())
                // Nothing else in this request hangs on an external call
                // without a timeout — revokeToken() below already sets one.
                // Without this, an unresponsive Discord API holds the worker
                // until max_execution_time instead of failing fast.
                ->setHttpClient(new \GuzzleHttp\Client(['timeout' => 5, 'connect_timeout' => 5]))
                ->user();
        } catch (\Throwable $e) {
            // Covers InvalidStateException and anything Discord or the
            // provider's user mapping throws. Reported, never shown.
            report($e);

            return redirect()->route('login')
                ->with('error', "Couldn't complete the Discord sign-in. Please try again.");
        }

        $discordId = (string) $discordUser->getId();
        $verified = ($discordUser->getRaw()['verified'] ?? false) === true;

        // Done with the token either way, and it is never persisted.
        $linker->revokeToken($discordUser->token ?? null);

        if (! $verified) {
            return redirect()->route('login')
                ->with('error', "Your Discord account's email address must be verified before you can sign in with it.");
        }

        $connection = UserConnectedAccount::where('provider', DiscordAccountLinker::PROVIDER)
            ->where('provider_user_id', $discordId)
            ->first();

        if ($connection && $connection->user) {
            return $this->signIn($request, $connection->user, 'existing link');
        }

        // No account yet — hold the identity and let them pick a nickname.
        // Nothing is written to the database until they confirm.
        $request->session()->put(self::IDENTITY_KEY, [
            'provider_user_id' => $discordId,
            'provider_nickname' => $discordUser->getNickname() ?: $discordUser->getName(),
            'expires_at' => now()->addMinutes(self::IDENTITY_TTL_MINUTES)->timestamp,
        ]);

        return redirect()->route('auth.discord.register');
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        $identity = $this->identity($request);

        if (! $identity) {
            return redirect()->route('login')
                ->with('error', 'Please start again from "Continue with Discord".');
        }

        return view('auth.discord-register', [
            'discordNickname' => $identity['provider_nickname'],
            'suggestedNickname' => NicknamePolicy::suggestFrom($identity['provider_nickname']),
        ]);
    }

    public function store(Request $request, DiscordAccountLinker $linker): RedirectResponse
    {
        $identity = $this->identity($request);

        if (! $identity) {
            return redirect()->route('login')
                ->with('error', 'Please start again from "Continue with Discord".');
        }

        $validated = $request->validate(
            ['nickname' => NicknamePolicy::rules()],
            ['nickname.regex' => 'Nicknames can only use letters, numbers, underscore, dot and hyphen.'],
        );

        try {
            $user = DB::transaction(function () use ($validated, $identity, $linker) {
                // Privilege columns are hard-coded, never read from the
                // request — same guarantee UserController::store gives, and
                // there is an existing test asserting that for that flow.
                $user = User::create([
                    'nickname' => $validated['nickname'],
                    'password' => null,
                    'is_admin' => false,
                    // false, not null — matches UserController::store, the
                    // existing plain-account creation path.
                    'trusted_user' => false,
                    'is_moderator' => false,
                ]);

                // Reuses the phase-1 linker so registration and linking share
                // one definition of a valid connection: verified email,
                // well-formed snowflake, not already claimed, unique index
                // as the real guarantee. A rejection rolls the user back.
                $linker->link($user, $identity['provider_user_id'], $identity['provider_nickname'], true);

                return $user;
            });
        } catch (AccountLinkRejected $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (QueryException $e) {
            // NicknamePolicy::isTaken() is a read before this write, so two
            // concurrent registrations can both pass validation for the same
            // nickname; the unique index on user.nickname is what actually
            // stops the second one. Narrowed to 23000 for the same reason
            // DiscordAccountLinker::link() narrows its own catch: anything
            // else is a real failure that should surface, not be reported as
            // a taken nickname.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            report($e);

            return back()->withInput()->with('error', 'That nickname is already taken.');
        }

        $request->session()->forget(self::IDENTITY_KEY);

        Log::info('Account registered via Discord.', [
            'user_iduser' => $user->iduser,
            'discord_user_id' => $identity['provider_user_id'],
        ]);

        return $this->signIn($request, $user, 'registration');
    }

    /**
     * The redirect URI must match what is registered in the Discord portal
     * byte for byte. Configured explicitly rather than derived from route()
     * because the site is served from the repo root through an
     * index.php/.htaccess bridge into laravel/public.
     */
    private function callbackUrl(): string
    {
        return (string) (config('services.discord.auth_redirect') ?: route('auth.discord.callback'));
    }

    private function signIn(Request $request, User $user, string $via): RedirectResponse
    {
        Auth::login($user);

        // Session fixation: the pre-login session id must not survive
        // becoming an authenticated session. AuthController::login does the
        // same after Auth::attempt.
        $request->session()->regenerate();

        Log::info('Signed in with Discord.', ['user_iduser' => $user->iduser, 'via' => $via]);

        return redirect()->intended(route('home'))->with('status', 'Logged in.');
    }

    private function consumeIntent(Request $request): bool
    {
        $intent = $request->session()->pull(self::INTENT_KEY);

        return is_array($intent) && (int) ($intent['expires_at'] ?? 0) >= now()->timestamp;
    }

    /**
     * @return array{provider_user_id: string, provider_nickname: ?string}|null
     */
    private function identity(Request $request): ?array
    {
        $identity = $request->session()->get(self::IDENTITY_KEY);

        if (! is_array($identity) || (int) ($identity['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::IDENTITY_KEY);

            return null;
        }

        return $identity;
    }
}
