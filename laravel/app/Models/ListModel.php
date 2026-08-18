<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListModel extends Model
{
    protected $table = 'list';

    protected $primaryKey = 'idlist';

    protected $fillable = ['list_name', 'game_idgame', 'password', 'type', 'user_iduser'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'game_idgame' => 'integer',
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

    public function categories(): HasMany
    {
        return $this->hasMany(ListCategory::class, 'list_idlist');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(ListPage::class, 'idList');
    }

    public function combos(): BelongsToMany
    {
        return $this->belongsToMany(Combo::class, 'combo_listing', 'idlist', 'idcombo')
            ->withPivot(['comment', 'list_category_idlist_category']);
    }
}
