<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Button extends Model
{
    protected $table = 'button';

    protected $primaryKey = 'idbutton';

    protected $fillable = ['name', 'color', 'match_type', 'game_idgame', 'order'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_idgame');
    }
}
