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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            'discordGuildCount' => $this->discordGuildCount(),
        ]);
    }

    /**
     * Discord doesn't push guild membership changes to us (the interactions
     * endpoint only receives command/component payloads), so the bot's
     * server count has to be pulled from the API on demand. Cached for 15
     * minutes so the admin page load doesn't hit Discord every time.
     * Cache::remember() re-runs the callback on a null result (Laravel's
     * cache stores can't distinguish "cached null" from "missing key"), so
     * a failed/unconfigured lookup naturally isn't pinned for the full
     * cache duration.
     *
     * Uses /users/@me/guilds rather than /applications/@me's
     * approximate_guild_count field — that field is only reliably populated
     * for verified/public bots and comes back null otherwise, which made
     * the count silently disappear instead of showing 0. limit=200 covers
     * every guild in one request as long as the bot is in 200 or fewer
     * servers; revisit with pagination (the `after` param) if it ever grows
     * past that.
     */
    private function discordGuildCount(): ?int
    {
        $botToken = config('services.discord.bot_token');

        if (! $botToken) {
            return null;
        }

        return Cache::remember('discord:guild_count', now()->addMinutes(15), function () use ($botToken) {
            $response = Http::withToken($botToken, 'Bot')
                ->timeout(5)
                ->get('https://discord.com/api/v10/users/@me/guilds', ['limit' => 200]);

            return $response->successful() ? count($response->json()) : null;
        });
    }
}
