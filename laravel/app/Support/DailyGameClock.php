<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The single "today" boundary for daily games (Comble puzzle, Daily
 * Challenge): rolls over at midnight GMT-3 (Brasília time), not the app's
 * default UTC clock, since that's the audience the daily cadence is tuned
 * for.
 */
class DailyGameClock
{
    public const TIMEZONE = 'America/Sao_Paulo';

    public static function today(): Carbon
    {
        return Carbon::now(self::TIMEZONE)->startOfDay();
    }
}
