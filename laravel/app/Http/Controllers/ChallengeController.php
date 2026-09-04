<?php

namespace App\Http\Controllers;

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
        $day = $this->resolveDate($date);
        $earliestDay = $this->dailyChallenge->earliestDate();
        $previousDay = $day->copy()->subDay();

        return view('challenge.show', [
            'challenge' => $this->dailyChallenge->forDate($day),
            'day' => $day,
            'isToday' => $day->isToday(),
            'earliestDay' => $earliestDay,
            'previousDay' => $earliestDay !== null && $previousDay->gte($earliestDay) ? $previousDay : null,
            'nextDay' => $day->isToday() ? null : $day->copy()->addDay(),
        ]);
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
     */
    public function calendarTab(Request $request): JsonResponse
    {
        $year = $request->integer('year');

        abort_unless($year >= 2000 && $year <= 2100, 404);

        $earliestDay = $this->dailyChallenge->earliestDate();
        $today = DailyGameClock::today();

        if ($earliestDay === null) {
            return response()->json(['days' => [], 'earliest' => null, 'today' => $today->toDateString()]);
        }

        $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, DailyGameClock::TIMEZONE)->startOfDay();
        $yearEnd = $yearStart->copy()->endOfYear()->startOfDay();

        if ($yearEnd->lt($earliestDay) || $yearStart->gt($today)) {
            return response()->json(['days' => [], 'earliest' => $earliestDay->toDateString(), 'today' => $today->toDateString()]);
        }

        $from = $yearStart->max($earliestDay);
        $to = $yearEnd->min($today);
        $trusted = (bool) auth()->user()?->isTrusted();

        // A plain array, not a Collection: even a Collection containing
        // nothing but strings still fails to unserialize correctly through
        // this app's file cache driver in practice — see
        // GameController::computeDamageStats()'s docblock and
        // ChallengeController::computeRankings()'s for the same failure
        // ("incomplete object... unserialize()") hit with cached Eloquent
        // models. A bare array of strings has no class to fail to load.
        $days = Cache::rememberForever(
            ChallengeStatsCache::calendarKey($year, $today->toDateString(), $trusted),
            fn () => $this->dailyChallenge->resultsBetween($from, $to, $trusted)->map(fn ($result) => match (true) {
                $result['query'] === null => 'no_query',
                $result['combo'] === null => 'open',
                default => 'solved',
            })->all()
        );

        return response()->json(['days' => $days, 'earliest' => $earliestDay->toDateString(), 'today' => $today->toDateString()]);
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
