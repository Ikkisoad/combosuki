<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonationProgress extends Model
{
    protected $table = 'donation_progress';

    protected $fillable = ['month', 'goal', 'raised'];

    protected $casts = [
        'goal' => 'decimal:2',
        'raised' => 'decimal:2',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'month' => now()->format('F Y'),
            'goal' => 0,
            'raised' => 0,
        ]);
    }
}
