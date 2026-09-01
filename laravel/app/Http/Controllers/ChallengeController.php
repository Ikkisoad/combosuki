<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DailyChallenge;
use App\Support\DailyGameClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $earliestDay = $this->dailyChallenge->earliestDate();

        if ($earliestDay === null) {
            return view('challenge.partials.ranking-tab', ['rankings' => collect()]);
        }

        $results = $this->dailyChallenge->resultsBetween($earliestDay, DailyGameClock::today());

        $winningCombos = $results->pluck('combo')->filter(fn ($combo) => $combo !== null && $combo->user_iduser !== null);
        $users = User::whereIn('iduser', $winningCombos->pluck('user_iduser')->unique())->get()->keyBy('iduser');

        $rankings = $winningCombos
            ->groupBy('user_iduser')
            ->map(fn ($combos, $userId) => ['user' => $users[$userId], 'wins' => $combos->count()])
            ->sortBy([
                ['wins', 'desc'],
                fn ($a, $b) => strcasecmp($a['user']->nickname, $b['user']->nickname),
            ])
            ->values();

        return view('challenge.partials.ranking-tab', compact('rankings'));
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

        $days = $this->dailyChallenge->resultsBetween($from, $to)->map(fn ($result) => match (true) {
            $result['query'] === null => 'no_query',
            $result['combo'] === null => 'open',
            default => 'solved',
        });

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
