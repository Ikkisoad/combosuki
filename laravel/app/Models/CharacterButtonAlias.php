<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterButtonAlias extends Model
{
    protected $table = 'character_button_alias';

    protected $primaryKey = 'idcharacterbuttonalias';

    protected $fillable = ['alias', 'button_idbutton', 'character_idcharacter'];

    public function button(): BelongsTo
    {
        return $this->belongsTo(Button::class, 'button_idbutton');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_idcharacter');
    }
}
