<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchResource extends Model
{
    protected $table = 'match_resources';

    protected $primaryKey = 'idmatch_resources';

    protected $fillable = [
        'match_idmatch',
        'game_resources_idgame_resources',
        'resources_values_idResources_values',
        'player',
    ];

    protected function casts(): array
    {
        return [
            'player' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_idmatch');
    }

    public function gameResource(): BelongsTo
    {
        return $this->belongsTo(GameResource::class, 'game_resources_idgame_resources');
    }

    public function resourceValue(): BelongsTo
    {
        return $this->belongsTo(ResourceValue::class, 'resources_values_idResources_values');
    }
}
