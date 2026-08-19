<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('user_search'), function ($query) use ($request) {
                $query->where('nickname', 'like', '%'.$request->string('user_search').'%');
            })
            ->orderBy('nickname')
            ->paginate(25, ['*'], 'user_page')
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nickname' => ['required', 'string', 'max:45', 'unique:user,nickname'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['sometimes', 'boolean'],
            'trusted_user' => ['sometimes', 'boolean'],
        ]);

        User::create([
            'nickname' => $validated['nickname'],
            'password' => $validated['password'],
            'is_admin' => $request->boolean('is_admin'),
            'trusted_user' => $request->boolean('trusted_user') ? 1 : null,
        ]);

        return redirect()->route('admin.users.index')->with('status', "User \"{$validated['nickname']}\" created.");
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => $validated['password']]);

        return redirect()->route('admin.users.index')->with('status', "Password updated for \"{$user->nickname}\".");
    }

    public function updateTrusted(User $user): RedirectResponse
    {
        $user->update(['trusted_user' => ! $user->trusted_user]);

        $status = $user->trusted_user ? 'trusted' : 'no longer trusted';

        return redirect()->route('admin.users.index')->with('status', "\"{$user->nickname}\" is now {$status}.");
    }
}
