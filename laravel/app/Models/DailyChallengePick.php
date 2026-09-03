<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyChallengePick extends Model
{
    protected $table = 'daily_challenge_picks';

    protected $primaryKey = 'iddaily_challenge_pick';

    protected $fillable = ['day', 'query_idquery', 'character_idcharacter'];

    protected function casts(): array
    {
        return [
            // Deliberately NOT cast to 'date' — see the identical comment on
            // CombleDayView::casts() for why: the date-cast setter reformats
            // to a full datetime string on write, which MySQL's DATE column
            // truncates away but SQLite stores verbatim, breaking day-string
            // equality lookups under tests.
            'query_idquery' => 'integer',
            'character_idcharacter' => 'integer',
        ];
    }

    public function characterQuery(): BelongsTo
    {
        return $this->belongsTo(CharacterQuery::class, 'query_idquery');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_idcharacter');
    }
}
