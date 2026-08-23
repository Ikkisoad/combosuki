<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TierList extends Model
{
    protected $table = 'tier_list';

    protected $primaryKey = 'idtier_list';

    protected $fillable = ['title', 'game_idgame', 'user_iduser'];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_idgame');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_iduser');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TierListEntry::class, 'tier_list_idtier_list')->orderBy('tier')->orderBy('order');
    }
}
