<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterLink extends Model
{
    protected $table = 'character_link';

    protected $primaryKey = 'idcharacterlink';

    protected $fillable = ['label', 'url', 'character_idcharacter', 'order'];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_idcharacter');
    }
}
