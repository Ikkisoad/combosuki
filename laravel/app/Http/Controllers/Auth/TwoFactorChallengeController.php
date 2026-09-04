<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * The second step of password login for an account with two-factor
 * authentication enabled. AuthController::login validates credentials with
 * Auth::validate() (which does not log in) and, for a 2FA account, parks the
 * user id here instead — nothing is granted until a valid TOTP code is
 * entered on this page. Guest-only, same as login itself.
 *
 * Deliberately not used by DiscordAuthController: 2FA gates password login
 * only, so signing in via Discord skips this step entirely.
 */
class TwoFactorChallengeController extends Controller
{
    private const PENDING_SESSION_KEY = 'two_factor_pending_user';

    private const PENDING_TTL_MINUTES = 5;

    public static function markPending(Request $request, User $user): void
    {
        $request->session()->put(self::PENDING_SESSION_KEY, [
            'user_iduser' => $user->iduser,
            'expires_at' => now()->addMinutes(self::PENDING_TTL_MINUTES)->timestamp,
        ]);
    }

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login')->with('error', 'Please log in again.');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactorAuthenticator $authenticator): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->with('error', 'Please log in again.');
        }

        $validated = $request->validate(['code' => ['required', 'string']]);
        $secret = $user->twoFactorSecret();

        if (! $secret || ! $authenticator->verify($secret, $validated['code'])) {
            return back()->with('error', 'That code is incorrect or has expired. Please try again.');
        }

        $request->session()->forget(self::PENDING_SESSION_KEY);

        // See AuthController::login for why this is remembered.
        Auth::login($user, true);

        // Session fixation: the pre-login session id must not survive
        // becoming an authenticated session. AuthController::login and
        // DiscordAuthController::signIn do the same after their own checks.
        $request->session()->regenerate();

        Log::info('Completed two-factor challenge.', ['user_iduser' => $user->iduser]);

        return redirect()->intended(route('home'))->with('status', 'Logged in.');
    }

    /**
     * Reads (not consumes) the marker so a GET reload or a failed code
     * doesn't burn the pending state — only a successful store() clears it,
     * bounding retries by the TTL and the route's own throttle instead.
     */
    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        if (! is_array($pending) || (int) ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::PENDING_SESSION_KEY);

            return null;
        }

        $user = User::find($pending['user_iduser'] ?? null);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(self::PENDING_SESSION_KEY);

            return null;
        }

        return $user;
    }
}
