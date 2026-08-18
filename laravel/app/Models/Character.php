<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    protected $table = 'character';

    protected $primaryKey = 'idcharacter';

    protected $fillable = ['name', 'game_idgame'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_idgame');
    }

    public function combos(): HasMany
    {
        return $this->hasMany(Combo::class, 'character_idcharacter');
    }
}
