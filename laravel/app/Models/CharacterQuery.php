<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CharacterQuery extends Model
{
    protected $table = 'character_default_queries';

    protected $primaryKey = 'idquery';

    protected $fillable = ['game_idgame', 'label', 'group_label', 'filters', 'order'];

    protected function casts(): array
    {
        return [
            'game_idgame' => 'integer',
            'filters' => 'array',
            'order' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_idgame');
    }

    /**
     * The characters this query is restricted to. Empty means it applies to
     * every character in the game (see CharacterController::show() and
     * GameController::damageStatsTab()).
     */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(
            Character::class,
            'character_default_query_character',
            'character_default_query_idquery',
            'character_idcharacter'
        );
    }
}
