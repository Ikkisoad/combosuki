<?php

namespace App\Models;

use App\Support\AliasGenerator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Game extends Model
{
    protected $table = 'game';

    protected $primaryKey = 'idgame';

    protected $fillable = [
        'name', 'complete', 'image', 'globalPass', 'modPass', 'description', 'notation',
        'matches_enabled', 'matches_url',
    ];

    protected $hidden = ['globalPass', 'modPass'];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'matches_enabled' => 'boolean',
        ];
    }

    /**
     * Auto-seed a best-effort alias (initials, or the first 5 letters for a
     * single-word name) on creation, for the Discord `/csk search` command.
     * Silently skipped on a case-insensitive collision — never blocks the
     * game from being created.
     */
    protected static function booted(): void
    {
        static::created(function (Game $game) {
            $alias = AliasGenerator::initials($game->name, 5);

            $exists = GameAlias::whereRaw('LOWER(alias) = ?', [Str::lower($alias)])->exists();

            if (! $exists) {
                $game->aliases()->create(['alias' => $alias]);
            }
        });
    }

    /**
     * The `image` column holds either a legacy external URL (free-text input,
     * before uploads existed) or a `public` disk path (from an admin upload).
     * This normalizes both into a displayable URL.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                ! $this->image => null,
                Str::startsWith($this->image, ['http://', 'https://']) => $this->image,
                default => Storage::url($this->image),
            },
        );
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(GameAlias::class, 'game_idgame');
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

    public function combos(): HasManyThrough
    {
        return $this->hasManyThrough(
            Combo::class,
            Character::class,
            'game_idgame',
            'character_idcharacter',
            'idgame',
            'idcharacter',
        );
    }

    public function buttons(): HasMany
    {
        return $this->hasMany(Button::class, 'game_idgame');
    }

    public function buttonAliases(): HasMany
    {
        return $this->hasMany(ButtonAlias::class, 'game_idgame');
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

    public function patches(): HasMany
    {
        return $this->hasMany(GamePatch::class, 'game_idgame')->orderByDesc('released_at');
    }

    /**
     * The single open-ended (ended_at IS NULL) patch for this game, if any.
     * A game may briefly have none right after creation, before any patch
     * has been registered via the admin Patches page.
     */
    public function currentPatch(): HasOne
    {
        return $this->hasOne(GamePatch::class, 'game_idgame')->whereNull('ended_at');
    }

    /**
     * The single primary resource (if any) flagged to expand tier lists by
     * value — e.g. one character card per moon type. At most one resource
     * per game may carry this flag.
     */
    public function tierListResource(): HasOne
    {
        return $this->hasOne(GameResource::class, 'game_idgame')->where('include_in_tier_lists', true);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'game_idgame');
    }

    public function moderators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'game_moderator', 'idgame', 'iduser');
    }
}
