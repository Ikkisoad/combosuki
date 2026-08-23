<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameAlias extends Model
{
    protected $table = 'game_alias';

    protected $primaryKey = 'idgamealias';

    protected $fillable = ['alias', 'game_idgame'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_idgame');
    }
}
