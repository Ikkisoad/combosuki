<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\ConfirmsPassword;
use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configuring TOTP two-factor authentication (Authy, Google Authenticator,
 * etc.) on the signed-in account. Lives in Auth\ next to PasswordController
 * and ConnectionController for the same reason: /account/* credential
 * management, gated behind the current password via ConfirmsPassword.
 *
 * A "pending" secret (set but not yet confirmed) does not count as enabled —
 * see User::hasTwoFactorEnabled() — so a user can start setup, walk away, and
 * come back to finish or restart without ever being gated at login.
 */
class TwoFactorController extends Controller
{
    use ConfirmsPassword;

    public function edit(Request $request, TwoFactorAuthenticator $authenticator): View
    {
        $user = $request->user();
        $enabled = $user->hasTwoFactorEnabled();
        $secret = $enabled ? null : $user->twoFactorSecret();
        $pending = $secret !== null;

        return view('account.two-factor', [
            'enabled' => $enabled,
            'pending' => $pending,
            'secret' => $secret,
            'qrCodeSvg' => $pending ? $authenticator->qrCodeSvg($secret, $user->nickname) : null,
        ]);
    }

    public function store(Request $request, TwoFactorAuthenticator $authenticator): RedirectResponse
    {
        // Without this, resubmitting this form on an already-enabled account
        // (double-submit, stale tab) silently replaces the confirmed secret
        // with a new, unscanned one — the authenticator app still has the
        // old one, and the account is locked out of its next login.
        if ($request->user()->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.edit');
        }

        $request->validate(['current_password' => ['required', 'string']]);

        if (! $this->passwordConfirmed($request)) {
            return back()->with('error', 'Incorrect password.');
        }

        // Not mass-assignable (see User::$fillable) — this must never be
        // settable from arbitrary request input, only from a freshly
        // generated secret here.
        $request->user()->forceFill(['two_factor_secret' => $authenticator->generateSecretKey()])->save();

        return redirect()->route('two-factor.edit')
            ->with('status', 'Scan the QR code with your authenticator app, then enter a code below to finish enabling.');
    }

    public function confirm(Request $request, TwoFactorAuthenticator $authenticator): RedirectResponse
    {
        $user = $request->user();
        $secret = $user->twoFactorSecret();

        if (! $secret || $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.edit');
        }

        $validated = $request->validate(['code' => ['required', 'string']]);

        if (! $authenticator->verify($secret, $validated['code'])) {
            return back()->with('error', 'That code is incorrect or has expired. Please try again.');
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return redirect()->route('two-factor.edit')->with('status', 'Two-factor authentication enabled.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'string']]);

        if (! $this->passwordConfirmed($request)) {
            return back()->with('error', 'Incorrect password.');
        }

        $user = $request->user();
        $wasEnabled = $user->hasTwoFactorEnabled();

        $user->disableTwoFactor();

        return redirect()->route('two-factor.edit')
            ->with('status', $wasEnabled ? 'Two-factor authentication disabled.' : 'Setup cancelled.');
    }
}
