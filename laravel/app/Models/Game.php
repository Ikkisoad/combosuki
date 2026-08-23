<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $table = 'game';

    protected $primaryKey = 'idgame';

    protected $fillable = [
        'name', 'complete', 'image', 'globalPass', 'modPass', 'patch', 'description', 'notation',
    ];

    protected $hidden = ['globalPass', 'modPass'];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
        ];
    }

    /**
     * The `complete` column encodes two flags at once: the sign marks whether
     * the game is complete (> 0) and the magnitude marks whether it is locked
     * (2 = complete + locked, -1 = incomplete + locked).
     */
    public function isComplete(): bool
    {
        return $this->complete > 0;
    }

    public function isLocked(): bool
    {
        return in_array((int) $this->complete, [2, -1], true);
    }

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class, 'game_idgame');
    }

    public function buttons(): HasMany
    {
        return $this->hasMany(Button::class, 'game_idgame');
    }

    public function gameResources(): HasMany
    {
        return $this->hasMany(GameResource::class, 'game_idgame');
    }

    /**
     * Alias for gameResources(), named to match the {resource} route
     * parameter so Laravel's scoped route-model binding (games/{game}/edit/
     * resources/{resource}) can resolve it by convention.
     */
    public function resources(): HasMany
    {
        return $this->gameResources();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(GameEntry::class, 'gameid');
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class, 'idGame');
    }

    public function lists(): HasMany
    {
        return $this->hasMany(ListModel::class, 'game_idgame');
    }

    public function characterQueries(): HasMany
    {
        return $this->hasMany(CharacterQuery::class, 'game_idgame');
    }

    public function tierLists(): HasMany
    {
        return $this->hasMany(TierList::class, 'game_idgame');
    }
}
