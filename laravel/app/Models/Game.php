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
}
