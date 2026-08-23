<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListModel;
use App\Models\TierList;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        $totals = [
            'games' => ['count' => Game::count(), 'views' => (int) Game::sum('views')],
            'combos' => ['count' => Combo::count(), 'views' => (int) Combo::sum('views')],
            'characters' => ['count' => Character::count(), 'views' => (int) Character::sum('views')],
            'guides' => ['count' => ListModel::count(), 'views' => (int) ListModel::sum('views')],
            'tierLists' => ['count' => TierList::count(), 'views' => (int) TierList::sum('views')],
        ];

        $topGames = Game::orderByDesc('views')->limit(10)->get(['idgame', 'name', 'views']);

        $topCombos = Combo::with('character.game')
            ->orderByDesc('views')
            ->limit(10)
            ->get(['idcombo', 'combo', 'character_idcharacter', 'views']);

        $topCharacters = Character::with('game')
            ->orderByDesc('views')
            ->limit(10)
            ->get(['idcharacter', 'name', 'game_idgame', 'views']);

        $topGuides = ListModel::with('game')
            ->orderByDesc('views')
            ->limit(10)
            ->get(['idlist', 'list_name', 'game_idgame', 'views']);

        $topTierLists = TierList::with('game')
            ->orderByDesc('views')
            ->limit(10)
            ->get(['idtier_list', 'title', 'game_idgame', 'views']);

        return view('admin.analytics.index', [
            'totals' => $totals,
            'topGames' => $topGames,
            'topCombos' => $topCombos,
            'topCharacters' => $topCharacters,
            'topGuides' => $topGuides,
            'topTierLists' => $topTierLists,
        ]);
    }
}
