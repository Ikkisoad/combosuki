<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Services\DailyChallenge;
use App\Support\ChallengeStatsCache;
use App\Support\DailyGameClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ChallengeController extends Controller
{
    public function __construct(private DailyChallenge $dailyChallenge) {}

    public function show(?string $date = null): View
    {
        return view('challenge.show', $this->dayViewData($this->resolveDate($date)));
    }

    /**
     * Just the day navigation + challenge card (challenge/partials/day),
     * fetched by challenge-day.js so browsing between days swaps that
     * section in place instead of reloading the whole page (and with it the
     * leaderboard/calendar tabs' already-loaded data).
     */
    public function dayPanel(string $date): View
    {
        return view('challenge.partials.day', $this->dayViewData($this->resolveDate($date)));
    }

    /**
     * @return array<string, mixed>
     */
    private function dayViewData(Carbon $day): array
    {
        $earliestDay = $this->dailyChallenge->earliestDate();
        $previousDay = $day->copy()->subDay();
        $isToday = $day->isToday();

        return [
            'challenge' => $this->dailyChallenge->forDate($day),
            'day' => $day,
            'today' => DailyGameClock::today(),
            'isToday' => $isToday,
            'pageTitle' => 'Challenge'.($isToday ? '' : ' — '.$day->format('M j, Y')).' - Combo好き',
            'earliestDay' => $earliestDay,
            'previousDay' => $earliestDay !== null && $previousDay->gte($earliestDay) ? $previousDay : null,
            'nextDay' => $isToday ? null : $day->copy()->addDay(),
        ];
    }

    /**
     * Ranks users by how many days their combo was the picked "top combo"
     * for that day's challenge, across every day a challenge has existed.
     * Guest-submitted combos (no user_iduser) can't be attributed to a
     * ranked user, so they're excluded rather than grouped under a
     * "guest" pseudo-row.
     */
    public function rankingTab(): View
    {
        $today = DailyGameClock::today();
        $trusted = (bool) auth()->user()?->isTrusted();

        $rankingsData = Cache::rememberForever(
            ChallengeStatsCache::rankingKey($today->toDateString(), $trusted),
            fn () => $this->computeRankings($today, $trusted)
        );

        $rankings = $this->hydrateRankings($rankingsData);

        return view('challenge.partials.ranking-tab', compact('rankings'));
    }

    /**
     * Returns plain ['user_id' => int, 'wins' => int] rows rather than User
     * models: this gets cached forever (see ChallengeStatsCache), and the
     * file cache driver (this app's production default — see .env.example)
     * serializes cached values with PHP's serialize(), which is fragile for
     * Eloquent models/Collections — see GameController::computeDamageStats()'s
     * docblock, which hit exactly this as "incomplete object... unserialize()"
     * once real requests round-tripped a cached User through it.
     * hydrateRankings() rebuilds the actual User objects after reading the
     * cache.
     *
     * @return list<array{user_id: int, wins: int}>
     */
    private function computeRankings(Carbon $today, bool $trusted): array
    {
        $earliestDay = $this->dailyChallenge->earliestDate();

        if ($earliestDay === null) {
            return [];
        }

        $results = $this->dailyChallenge->resultsBetween($earliestDay, $today, $trusted);

        $winningCombos = $results->pluck('combo')->filter(fn ($combo) => $combo !== null && $combo->user_iduser !== null);

        return $winningCombos
            ->groupBy('user_iduser')
            ->map(fn ($combos, $userId) => ['user_id' => (int) $userId, 'wins' => $combos->count()])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{user_id: int, wins: int}>  $rankingsData
     */
    private function hydrateRankings(array $rankingsData): Collection
    {
        $users = User::whereIn('iduser', collect($rankingsData)->pluck('user_id'))->get()->keyBy('iduser');

        return collect($rankingsData)
            ->map(fn (array $entry) => ['user' => $users->get($entry['user_id']), 'wins' => $entry['wins']])
            // A ranked user deleted after their win was cached would
            // otherwise render with a null user — drop them rather than
            // erroring, same as if they'd never won at all.
            ->filter(fn (array $entry) => $entry['user'] !== null)
            ->sortBy([
                ['wins', 'desc'],
                fn ($a, $b) => strcasecmp($a['user']->nickname, $b['user']->nickname),
            ])
            ->values();
    }

    /**
     * Per-day status for the requested calendar year, clamped to the range a
     * challenge could ever have existed in ([earliestDate(), today]): days
     * outside that range are simply absent from the response, which the
     * calendar's JS treats as "unavailable" rather than any of the three
     * real statuses.
     *
     * Also returns which game each day's challenge belonged to (day_games,
     * date => game id, only for days that had a challenge) plus the games
     * appearing in that year (games, sorted by name), which the calendar's
     * game filter uses to highlight only the days a given game was picked.
     */
    public function calendarTab(Request $request): JsonResponse
    {
        $year = $request->integer('year');

        abort_unless($year >= 2000 && $year <= 2100, 404);

        $earliestDay = $this->dailyChallenge->earliestDate();
        $today = DailyGameClock::today();

        $empty = ['days' => [], 'day_games' => [], 'games' => [], 'earliest' => $earliestDay?->toDateString(), 'today' => $today->toDateString()];

        if ($earliestDay === null) {
            return response()->json($empty);
        }

        $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, DailyGameClock::TIMEZONE)->startOfDay();
        $yearEnd = $yearStart->copy()->endOfYear()->startOfDay();

        if ($yearEnd->lt($earliestDay) || $yearStart->gt($today)) {
            return response()->json($empty);
        }

        $from = $yearStart->max($earliestDay);
        $to = $yearEnd->min($today);
        $trusted = (bool) auth()->user()?->isTrusted();

        // Plain arrays, not Collections/models: even a Collection containing
        // nothing but strings still fails to unserialize correctly through
        // this app's file cache driver in practice — see
        // GameController::computeDamageStats()'s docblock and
        // ChallengeController::computeRankings()'s for the same failure
        // ("incomplete object... unserialize()") hit with cached Eloquent
        // models. Game ids are cached rather than Game models for the same
        // reason; the games list is hydrated after the cache read.
        $calendar = Cache::rememberForever(
            ChallengeStatsCache::calendarKey($year, $today->toDateString(), $trusted),
            fn () => $this->computeCalendar($from, $to, $trusted)
        );

        $games = Game::whereIn('idgame', array_unique(array_values($calendar['day_games'])))
            ->orderBy('name')
            ->get(['idgame', 'name'])
            ->map(fn (Game $game) => ['id' => (int) $game->idgame, 'name' => $game->name])
            ->values()
            ->all();

        return response()->json([
            'days' => $calendar['days'],
            'day_games' => $calendar['day_games'],
            'games' => $games,
            'earliest' => $earliestDay->toDateString(),
            'today' => $today->toDateString(),
        ]);
    }

    /**
     * @return array{days: array<string, string>, day_games: array<string, int>}
     */
    private function computeCalendar(Carbon $from, Carbon $to, bool $trusted): array
    {
        $results = $this->dailyChallenge->resultsBetween($from, $to, $trusted);

        return [
            'days' => $results->map(fn ($result) => match (true) {
                $result['query'] === null => 'no_query',
                $result['combo'] === null => 'open',
                default => 'solved',
            })->all(),
            'day_games' => $results
                ->filter(fn ($result) => $result['query'] !== null)
                ->map(fn ($result) => (int) $result['query']->game_idgame)
                ->all(),
        ];
    }

    /**
     * Same rules as CombleController::resolveDate — no lower bound, since
     * DailyChallenge::forDate is a pure function of the currently-eligible
     * query pool for any given date. Future dates and malformed calendar
     * dates (e.g. Feb 30, which the route's \d{4}-\d{2}-\d{2} pattern lets
     * through) 404.
     */
    private function resolveDate(?string $date): Carbon
    {
        if ($date === null) {
            return DailyGameClock::today();
        }

        try {
            $day = Carbon::createFromFormat('!Y-m-d', $date, DailyGameClock::TIMEZONE);
        } catch (\Throwable) {
            abort(404);
        }

        abort_if($day->format('Y-m-d') !== $date, 404);
        abort_if($day->gt(DailyGameClock::today()), 404);

        return $day;
    }
}
