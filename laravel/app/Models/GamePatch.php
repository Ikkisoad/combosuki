<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamePatch extends Model
{
    protected $table = 'game_patches';

    protected $primaryKey = 'idgame_patch';

    protected $fillable = ['game_idgame', 'label', 'released_at', 'ended_at'];

    protected function casts(): array
    {
        return [
            'released_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_idgame');
    }

    public function combos(): HasMany
    {
        return $this->hasMany(Combo::class, 'patch_idgame_patch');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }
}
