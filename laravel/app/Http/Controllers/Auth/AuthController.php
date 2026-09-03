<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use RuntimeException;

class AuthController extends Controller
{
    /**
     * A precomputed bcrypt hash (cost 12, matching BCRYPT_ROUNDS) of a value
     * nobody's password will ever be. Exists purely to be Hash::check()'d
     * against so the passwordless-account branch below spends the same time
     * as Auth::attempt() would — see the comment there for why.
     */
    private const DUMMY_HASH = '$2y$12$WpoMgFIeb.OlKYUEb3EXyuwsvPK.YBN0cFquJUSEP17T6HbUgnSGu';

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'nickname' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // A Discord-registered account has no password at all. Auth::attempt
        // would already fail on an empty hash, but that is a side effect of
        // the hasher rather than a rule — make it explicit, and reuse the
        // same generic message so this can't be used to find out which
        // accounts are Discord-only. Looked up case-insensitively for the
        // same reason NicknamePolicy::isTaken() is: MySQL's _ci collation
        // already treats "Bob" and "bob" as the same nickname in production.
        $existing = User::whereRaw('LOWER(nickname) = ?', [mb_strtolower($credentials['nickname'])])->first();

        if ($existing && ! $existing->hasUsablePassword()) {
            // Message alone isn't enough: without this, the branch below
            // returns near-instantly while Auth::attempt() always pays
            // bcrypt's cost on a wrong-password guess, and that timing gap is
            // itself an oracle for which accounts are Discord-only.
            Hash::check($credentials['password'], self::DUMMY_HASH);

            return back()->withInput($request->only('nickname'))->with('error', 'Incorrect nickname or password.');
        }

        try {
            // validate(), not attempt(): it runs the same constant-time
            // check but does not log in or fire the Login event, so a
            // two-factor account isn't granted a session (and doesn't get
            // its last_login_at bumped, see AppServiceProvider) until the
            // challenge below is actually passed.
            $authenticated = Auth::validate($credentials);
        } catch (RuntimeException) {
            // The stored password hash isn't a valid bcrypt hash (e.g. it was set via a
            // query builder mass update, which bypasses the `hashed` cast on User).
            $authenticated = false;
        }

        if (! $authenticated) {
            return back()->withInput($request->only('nickname'))->with('error', 'Incorrect nickname or password.');
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();

        // Auth::attempt() used to do this as a side effect of logging in;
        // validate() doesn't, so it has to be done explicitly now, or a
        // stale bcrypt cost (e.g. after raising BCRYPT_ROUNDS) would never
        // get upgraded on any password login again.
        Auth::getProvider()->rehashPasswordIfRequired($user, $credentials);

        if ($user->hasTwoFactorEnabled()) {
            TwoFactorChallengeController::markPending($request, $user);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('status', 'Logged in.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Logged out.');
    }
}
