<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\GameMatch;
use App\Models\User;
use App\Services\UserStats;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(User $user, UserStats $stats): View
    {
        $mostViewedCombos = Combo::where('user_iduser', $user->iduser)
            ->with(['character.game', 'listingType'])
            ->visibleTo(auth()->user())
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        $matches = GameMatch::where(function ($query) use ($user) {
            $query->where('player_one_user_iduser', $user->iduser)
                ->orWhere('player_two_user_iduser', $user->iduser);
        })
            ->with(['game', 'playerOneCharacter', 'playerTwoCharacter', 'playerOneUser', 'playerTwoUser'])
            ->orderByDesc('played_at')
            ->limit(10)
            ->get();

        return view('users.show', [
            'user' => $user,
            'mostViewedCombos' => $mostViewedCombos,
            'matches' => $matches,
            'stats' => $stats->summary($user),
            'isOwnProfile' => auth()->check() && auth()->user()->is($user),
        ]);
    }
}
