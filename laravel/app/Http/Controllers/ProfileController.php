<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\User;
use App\Services\UserStats;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(User $user, UserStats $stats): View
    {
        $mostViewedCombos = Combo::where('user_iduser', $user->iduser)
            ->with(['character.game', 'listingType'])
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        return view('users.show', [
            'user' => $user,
            'mostViewedCombos' => $mostViewedCombos,
            'stats' => $stats->summary($user),
            'isOwnProfile' => auth()->check() && auth()->user()->is($user),
        ]);
    }
}
