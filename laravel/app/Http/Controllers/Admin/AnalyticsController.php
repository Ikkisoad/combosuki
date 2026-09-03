<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotHit;
use App\Models\Character;
use App\Models\Combo;
use App\Models\CombleDayView;
use App\Models\DiscordCommandUsage;
use App\Models\Game;
use App\Models\ListModel;
use App\Models\TierList;
use App\Services\CombleDailyCombo;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(private CombleDailyCombo $dailyCombo) {}

    public function index(): View
    {
        $totals = [
            'games' => ['count' => Game::count(), 'views' => (int) Game::sum('views')],
            'combos' => ['count' => Combo::count(), 'views' => (int) Combo::sum('views')],
            'characters' => ['count' => Character::count(), 'views' => (int) Character::sum('views')],
            'guides' => ['count' => ListModel::count(), 'views' => (int) ListModel::sum('views')],
            'tierLists' => ['count' => TierList::count(), 'views' => (int) TierList::sum('views')],
            'combleDays' => ['count' => CombleDayView::count(), 'views' => (int) CombleDayView::sum('views')],
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

        $topCombleDays = CombleDayView::orderByDesc('views')
            ->limit(10)
            ->get(['day', 'views'])
            ->map(function (CombleDayView $dayView) {
                $day = Carbon::parse($dayView->day);
                $target = $this->dailyCombo->forDate($day);

                return [
                    'day' => $day,
                    'views' => $dayView->views,
                    'game' => $target->character->game,
                    'character' => $target->character,
                ];
            });

        $topBotPages = BotHit::selectRaw('path, COUNT(*) as hits')
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->groupBy('path')
            ->orderByDesc('hits')
            ->limit(10)
            ->get();

        $topDiscordCommands = DiscordCommandUsage::orderByDesc('uses')->limit(10)->get(['command', 'uses']);

        return view('admin.analytics.index', [
            'totals' => $totals,
            'topGames' => $topGames,
            'topCombos' => $topCombos,
            'topCharacters' => $topCharacters,
            'topGuides' => $topGuides,
            'topTierLists' => $topTierLists,
            'topCombleDays' => $topCombleDays,
            'topBotPages' => $topBotPages,
            'totalBotHits' => BotHit::count(),
            'topDiscordCommands' => $topDiscordCommands,
            'totalDiscordCommandUses' => (int) DiscordCommandUsage::sum('uses'),
        ]);
    }
}
