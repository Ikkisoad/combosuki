<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterAlias extends Model
{
    protected $table = 'character_alias';

    protected $primaryKey = 'idcharacteralias';

    protected $fillable = ['alias', 'character_idcharacter', 'game_idgame'];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_idcharacter');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_idgame');
    }
}
