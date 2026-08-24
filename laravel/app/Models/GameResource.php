<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameResource extends Model
{
    protected $table = 'game_resources';

    protected $primaryKey = 'idgame_resources';

    protected $fillable = ['game_idgame', 'text_name', 'type', 'primaryORsecundary'];

    protected function casts(): array
    {
        return [
            'game_idgame' => 'integer',
            'type' => 'integer',
            'primaryORsecundary' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_idgame');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ResourceValue::class, 'game_resources_idgame_resources');
    }

    /**
     * The characters this resource is scoped to. Empty means unrestricted —
     * shown for every character (only consulted for secondary resources;
     * primary resources always show regardless of these links).
     */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_game_resources', 'game_resources_idgame_resources', 'character_idcharacter');
    }
}
