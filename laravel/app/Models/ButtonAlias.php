<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ButtonAlias extends Model
{
    protected $table = 'button_alias';

    protected $primaryKey = 'idbuttonalias';

    protected $fillable = ['alias', 'button_idbutton', 'game_idgame'];

    public function button(): BelongsTo
    {
        return $this->belongsTo(Button::class, 'button_idbutton');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_idgame');
    }
}
