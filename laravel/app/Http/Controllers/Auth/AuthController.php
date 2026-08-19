<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class AuthController extends Controller
{
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

        try {
            $authenticated = Auth::attempt($credentials);
        } catch (RuntimeException) {
            // The stored password hash isn't a valid bcrypt hash (e.g. it was set via a
            // query builder mass update, which bypasses the `hashed` cast on User).
            $authenticated = false;
        }

        if (! $authenticated) {
            return back()->withInput($request->only('nickname'))->with('error', 'Incorrect nickname or password.');
        }

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
