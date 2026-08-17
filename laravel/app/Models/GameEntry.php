<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameEntry extends Model
{
    protected $table = 'game_entry';

    protected $primaryKey = 'entryid';

    protected $fillable = ['title', 'gameid', 'order'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'gameid');
    }
}
