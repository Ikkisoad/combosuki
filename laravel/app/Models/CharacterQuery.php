<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
