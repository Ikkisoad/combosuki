<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TierListEntry extends Model
{
    /**
     * Every tier a list can use, best to worst. Single source of truth for the
     * maker board, validation, aggregation and the Discord image renderer.
     */
    public const TIERS = ['S', 'A', 'B', 'C', 'D', 'E', 'F'];

    protected $table = 'tier_list_entry';

    protected $primaryKey = 'idtier_list_entry';

    protected $fillable = ['tier_list_idtier_list', 'character_idcharacter', 'resources_values_idResources_values', 'tier', 'order'];

    public function tierList(): BelongsTo
    {
        return $this->belongsTo(TierList::class, 'tier_list_idtier_list');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_idcharacter');
    }

    public function resourceValue(): BelongsTo
    {
        return $this->belongsTo(ResourceValue::class, 'resources_values_idResources_values');
    }
}
