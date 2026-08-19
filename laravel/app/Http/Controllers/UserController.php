<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function create(): View
    {
        return view('users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nickname' => ['required', 'string', 'max:45', 'unique:user,nickname'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'nickname' => $validated['nickname'],
            'password' => $validated['password'],
            'is_admin' => false,
            'trusted_user' => false,
        ]);

        return redirect()->route('users.create')->with('status', "User \"{$validated['nickname']}\" created.");
    }
}
