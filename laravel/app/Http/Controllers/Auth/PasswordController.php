<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\ConfirmsPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordController extends Controller
{
    use ConfirmsPassword;

    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $this->passwordConfirmed($request)) {
            return back()->with('error', 'Incorrect password.');
        }

        $request->user()->update(['password' => $validated['password']]);

        return redirect()->route('password.edit')->with('status', 'Password updated.');
    }
}
