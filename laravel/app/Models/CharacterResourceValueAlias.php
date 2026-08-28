<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterResourceValueAlias extends Model
{
    protected $table = 'character_resource_value_alias';

    protected $primaryKey = 'idcharacterresourcevaluealias';

    protected $fillable = ['alias', 'icon', 'character_idcharacter', 'resources_values_idResources_values'];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_idcharacter');
    }

    public function resourceValue(): BelongsTo
    {
        return $this->belongsTo(ResourceValue::class, 'resources_values_idResources_values');
    }
}
