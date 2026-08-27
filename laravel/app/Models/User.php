<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'user';

    protected $primaryKey = 'iduser';

    protected $fillable = ['nickname', 'trusted_user', 'password', 'is_admin', 'is_moderator'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'trusted_user' => 'boolean',
            'is_moderator' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Trusted-level access to everything except game editing, which is
     * scoped per-game via moderatedGames() (see GamePolicy).
     */
    public function isTrusted(): bool
    {
        return $this->is_admin || $this->trusted_user || $this->is_moderator;
    }

    /**
     * Gates the admin-user-management carve-outs a moderator gets (viewing
     * the user list, toggling another user's trusted flag) without granting
     * the rest of the admin dashboard.
     */
    public function isModerator(): bool
    {
        return $this->is_admin || $this->is_moderator;
    }

    public function moderatedGames(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_moderator', 'iduser', 'idgame');
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

    public function connectedAccounts(): HasMany
    {
        return $this->hasMany(UserConnectedAccount::class, 'user_iduser');
    }

    public function discordAccount(): HasOne
    {
        return $this->hasOne(UserConnectedAccount::class, 'user_iduser')->where('provider', 'discord');
    }

    /**
     * Accounts predating the password column (and any row whose password was
     * cleared) can't satisfy the current-password check that gates account
     * linking, so the connections page offers them a "set a password first"
     * message instead of a form that could only ever fail.
     */
    public function hasUsablePassword(): bool
    {
        return is_string($this->password) && $this->password !== '';
    }
}
