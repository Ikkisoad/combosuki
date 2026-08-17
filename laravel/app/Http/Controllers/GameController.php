<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        $games = Game::orderBy('name')->get();

        return view('games.index', ['games' => $games]);
    }

    public function show(Game $game): View
    {
        $game->load(['links' => fn ($query) => $query->orderBy('Title')]);

        $characters = Character::where('game_idgame', $game->idgame)
            ->withCount('combos')
            ->having('combos_count', '>', 0)
            ->orderByDesc('combos_count')
            ->orderBy('name')
            ->get();

        $latestCombos = Combo::with(['character', 'listingType'])
            ->whereHas('character', fn ($query) => $query->where('game_idgame', $game->idgame))
            ->orderByDesc('submited')
            ->limit(5)
            ->get();

        return view('games.show', [
            'game' => $game,
            'characters' => $characters,
            'latestCombos' => $latestCombos,
        ]);
    }

}
