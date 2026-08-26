<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CombleAttempt extends Model
{
    protected $table = 'comble_attempts';

    protected $primaryKey = 'idcomble_attempt';

    public $timestamps = false;

    protected $fillable = ['day', 'user_iduser', 'visitor_key', 'guesses', 'won', 'perfect'];

    protected function casts(): array
    {
        return [
            // Deliberately NOT cast to 'date' — see the identical comment on
            // CombleDayView::casts() for why: the date-cast setter reformats
            // to a full datetime string on write, which MySQL's DATE column
            // truncates away but SQLite stores verbatim, breaking day-string
            // equality lookups (e.g. CombleStats::summary()) under tests.
            'guesses' => 'integer',
            'won' => 'boolean',
            'perfect' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_iduser');
    }
}
