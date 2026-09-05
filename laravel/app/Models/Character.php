<?php

namespace App\Models;

use App\Support\AliasGenerator;
use App\Support\DamageStatsCache;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Character extends Model
{
    protected $table = 'character';

    protected $primaryKey = 'idcharacter';

    protected $fillable = ['name', 'image', 'game_idgame', 'views'];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
        ];
    }

    /**
     * Auto-seed a best-effort alias (initials, or the first 3 letters for a
     * single-word name) on creation, for the Discord `/csk search` command.
     * Silently skipped on a case-insensitive collision within the same
     * game — never blocks the character from being created.
     */
    protected static function booted(): void
    {
        static::created(function (Character $character) {
            $alias = AliasGenerator::initials($character->name, 3);

            $exists = CharacterAlias::where('game_idgame', $character->game_idgame)
                ->whereRaw('LOWER(alias) = ?', [Str::lower($alias)])
                ->exists();

            if (! $exists) {
                $character->aliases()->create([
                    'alias' => $alias,
                    'game_idgame' => $character->game_idgame,
                ]);
            }
        });

        // GameController::damageStatsTab() caches its per-game aggregation
        // forever (see DamageStatsCache), and it lists every character in
        // the game (with a name and, for a new arrival, "No data" until they
        // have combos) — so the roster changing, not just Combo/CharacterQuery
        // writes, has to invalidate it too.
        static::saved(fn (Character $character) => DamageStatsCache::forget($character->game_idgame));
        static::deleted(fn (Character $character) => DamageStatsCache::forget($character->game_idgame));
    }

    /**
     * The `image` column holds either a legacy external URL (free-text input,
     * before uploads existed) or a `public` disk path (from an admin upload).
     * This normalizes both into a displayable URL, mirroring Game::logoUrl().
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => match (true) {
                ! $this->image => null,
                Str::startsWith($this->image, ['http://', 'https://']) => $this->image,
                default => Storage::url($this->image),
            },
        );
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_idgame');
    }

    public function combos(): HasMany
    {
        return $this->hasMany(Combo::class, 'character_idcharacter');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(CharacterAlias::class, 'character_idcharacter');
    }

    public function links(): HasMany
    {
        return $this->hasMany(CharacterLink::class, 'character_idcharacter')->orderBy('order');
    }

    public function gameResources(): BelongsToMany
    {
        return $this->belongsToMany(GameResource::class, 'character_game_resources', 'character_idcharacter', 'game_resources_idgame_resources');
    }

    public function resourceValueAliases(): HasMany
    {
        return $this->hasMany(CharacterResourceValueAlias::class, 'character_idcharacter');
    }

    public function buttonAliases(): HasMany
    {
        return $this->hasMany(CharacterButtonAlias::class, 'character_idcharacter');
    }

    public function defaultQueries(): BelongsToMany
    {
        return $this->belongsToMany(
            CharacterQuery::class,
            'character_default_query_character',
            'character_idcharacter',
            'character_default_query_idquery'
        );
    }

    /**
     * Guides a moderator has curated to appear on this character's page as
     * a "Featured Guide" suggestion — see CharacterController::show() and
     * Admin\GameListController.
     */
    public function featuredGuides(): BelongsToMany
    {
        return $this->belongsToMany(ListModel::class, 'character_featured_guide', 'character_idcharacter', 'list_idlist');
    }
}
