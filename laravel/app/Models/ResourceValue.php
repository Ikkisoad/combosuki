<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceValue extends Model
{
    protected $table = 'resources_values';

    protected $primaryKey = 'idResources_values';

    protected $fillable = ['value', 'order', 'icon', 'game_resources_idgame_resources'];

    public function gameResource(): BelongsTo
    {
        return $this->belongsTo(GameResource::class, 'game_resources_idgame_resources');
    }
}
