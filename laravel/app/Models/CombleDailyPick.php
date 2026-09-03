<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CombleDailyPick extends Model
{
    protected $table = 'comble_daily_picks';

    protected $primaryKey = 'idcomble_daily_pick';

    protected $fillable = ['day', 'combo_idcombo'];

    protected function casts(): array
    {
        return [
            // Deliberately NOT cast to 'date' — see the identical comment on
            // CombleDayView::casts() for why: the date-cast setter reformats
            // to a full datetime string on write, which MySQL's DATE column
            // truncates away but SQLite stores verbatim, breaking day-string
            // equality lookups under tests.
            'combo_idcombo' => 'integer',
        ];
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class, 'combo_idcombo');
    }
}
