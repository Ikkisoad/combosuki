<?php

namespace App\Models;

use App\Models\Concerns\HasEditHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListModel extends Model
{
    use HasEditHistory;

    protected $table = 'list';

    protected $primaryKey = 'idlist';

    protected $fillable = ['list_name', 'game_idgame', 'password', 'type', 'user_iduser', 'is_favorite_guide', 'views'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'game_idgame' => 'integer',
            'views' => 'integer',
            'is_favorite_guide' => 'boolean',
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

    /**
     * Characters whose page this guide is curated to appear on as a
     * "Featured Guide" suggestion — see CharacterController::show() and
     * Admin\GameListController. Independent of the game-wide $type == 3
     * "Featured" flag shown on the game's Guides tab.
     */
    public function featuredForCharacters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_featured_guide', 'list_idlist', 'character_idcharacter');
    }
}
