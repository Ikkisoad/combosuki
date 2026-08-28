<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceValue extends Model
{
    protected $table = 'resources_values';

    protected $primaryKey = 'idResources_values';

    protected $fillable = ['value', 'order', 'icon', 'game_resources_idgame_resources'];

    public function gameResource(): BelongsTo
    {
        return $this->belongsTo(GameResource::class, 'game_resources_idgame_resources');
    }

    public function characterAliases(): HasMany
    {
        return $this->hasMany(CharacterResourceValueAlias::class, 'resources_values_idResources_values');
    }

    /**
     * The per-character override for this value's display (alias text and/or
     * icon), or null if $character has none — callers should fall back to
     * $this->value / $this->icon. Uses the already-loaded characterAliases
     * relation when available to avoid N+1 queries in loops.
     */
    public function aliasFor(?Character $character): ?CharacterResourceValueAlias
    {
        if (! $character) {
            return null;
        }

        if ($this->relationLoaded('characterAliases')) {
            return $this->characterAliases->firstWhere('character_idcharacter', $character->idcharacter);
        }

        return $this->characterAliases()->where('character_idcharacter', $character->idcharacter)->first();
    }
}
