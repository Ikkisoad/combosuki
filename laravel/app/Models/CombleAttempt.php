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
            'day' => 'date',
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
