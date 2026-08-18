<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resource extends Model
{
    protected $table = 'resources';

    protected $primaryKey = 'idResources';

    protected $fillable = ['combo_idcombo', 'Resources_values_idResources_values', 'number_value'];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class, 'combo_idcombo');
    }

    public function resourceValue(): BelongsTo
    {
        return $this->belongsTo(ResourceValue::class, 'Resources_values_idResources_values');
    }
}
