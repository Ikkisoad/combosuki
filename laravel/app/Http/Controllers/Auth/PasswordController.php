<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\ConfirmsPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PasswordController extends Controller
{
    use ConfirmsPassword;

    public function edit(Request $request): View
    {
        return view('auth.password', [
            'hasPassword' => $request->user()->hasUsablePassword(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Branches on what the database says, never on request input: a
        // forged field must not be able to skip the current-password check.
        $settingFirstPassword = ! $request->user()->hasUsablePassword();

        $validated = $request->validate([
            'current_password' => $settingFirstPassword ? ['nullable'] : ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Nothing to confirm when there is no password yet — this is the only
        // way a Discord-registered account can ever get one, and being signed
        // in is already proof of the Discord credential.
        if (! $settingFirstPassword && ! $this->passwordConfirmed($request)) {
            return back()->with('error', 'Incorrect password.');
        }

        $request->user()->update(['password' => $validated['password']]);

        if ($settingFirstPassword) {
            Log::info('First password set on an account.', ['user_iduser' => $request->user()->iduser]);
        }

        return redirect()->route('password.edit')
            ->with('status', $settingFirstPassword ? 'Password set.' : 'Password updated.');
    }
}
