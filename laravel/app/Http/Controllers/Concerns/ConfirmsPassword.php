<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Re-authentication for account-settings actions.
 *
 * Deliberately Hash::check() rather than the `current_password` validation
 * rule: some legacy rows hold a value that isn't a valid bcrypt hash (set by
 * a query-builder mass update that bypassed the User model's `hashed` cast),
 * and the rule surfaces that as an uncaught RuntimeException — a 500 — rather
 * than a failed check. AuthController::login already wraps Auth::attempt()
 * in the same guard for the same reason.
 */
trait ConfirmsPassword
{
    protected function passwordConfirmed(Request $request): bool
    {
        $stored = $request->user()->password;

        if (! is_string($stored) || $stored === '') {
            return false;
        }

        try {
            return Hash::check((string) $request->input('current_password'), $stored);
        } catch (RuntimeException) {
            return false;
        }
    }
}
