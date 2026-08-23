<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'user';

    protected $primaryKey = 'iduser';

    protected $fillable = ['nickname', 'trusted_user', 'password', 'is_admin'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'trusted_user' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isTrusted(): bool
    {
        return $this->is_admin || $this->trusted_user;
    }

    public function combos(): HasMany
    {
        return $this->hasMany(Combo::class, 'user_iduser');
    }

    public function lists(): HasMany
    {
        return $this->hasMany(ListModel::class, 'user_iduser');
    }

    public function favoriteGuide(): HasOne
    {
        return $this->hasOne(ListModel::class, 'user_iduser')->where('is_favorite_guide', true);
    }

    public function getOrCreateFavoriteGuide(): ListModel
    {
        return ListModel::firstOrCreate(
            ['user_iduser' => $this->iduser, 'is_favorite_guide' => true],
            ['list_name' => 'Favorites', 'game_idgame' => null, 'type' => 0, 'password' => ''],
        );
    }

    public function tierLists(): HasMany
    {
        return $this->hasMany(TierList::class, 'user_iduser');
    }
}
