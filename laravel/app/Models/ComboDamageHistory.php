<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboDamageHistory extends Model
{
    protected $fillable = ['combo_idcombo', 'patch_idgame_patch', 'damage'];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class, 'combo_idcombo');
    }

    public function patch(): BelongsTo
    {
        return $this->belongsTo(GamePatch::class, 'patch_idgame_patch');
    }
}
