<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CombleDayView extends Model
{
    protected $table = 'comble_day_views';

    protected $primaryKey = 'idcomble_day_view';

    public $timestamps = false;

    protected $fillable = ['day', 'views'];

    protected function casts(): array
    {
        return [
            // Deliberately NOT cast to 'date': Eloquent's date-cast setter
            // reformats the value to a full datetime string on write (e.g.
            // "2026-08-23 00:00:00"), which doesn't match the plain
            // "2026-08-23" string firstOrCreate()'s raw WHERE array searches
            // for — MySQL's DATE column silently truncates the mismatch away,
            // but SQLite stores it verbatim, so recordView() would insert a
            // duplicate (and violate the unique index) on every call after
            // the first. Keeping "day" a plain string sidesteps that
            // read/write asymmetry entirely; callers pass/receive plain
            // Y-m-d strings.
            'views' => 'integer',
        ];
    }

    /**
     * Shared by the web (CombleController::show()) and Discord
     * (DiscordCombleGame::start()) Comble entry points, so a puzzle viewed
     * either way counts toward the same per-day total.
     */
    public static function recordView(Carbon $day): void
    {
        static::query()->firstOrCreate(['day' => $day->toDateString()], ['views' => 0])->increment('views');
    }
}
