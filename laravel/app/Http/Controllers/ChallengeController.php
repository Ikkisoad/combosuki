<?php

namespace App\Http\Controllers;

use App\Services\DailyChallenge;
use App\Support\DailyGameClock;
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
