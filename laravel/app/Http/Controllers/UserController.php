<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function create(): View
    {
        return view('users.create');
    }

    /**
     * Nickname typeahead for linking a match participant to their account
     * (see components.matches.player-fields) without loading every user
     * into a giant <select>.
     */
    public function search(Request $request): JsonResponse
    {
        $search = trim($request->string('q'));

        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $users = User::where('nickname', 'like', '%'.$search.'%')
            ->orderBy('nickname')
            ->limit(10)
            ->get(['iduser', 'nickname']);

        return response()->json($users);
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
