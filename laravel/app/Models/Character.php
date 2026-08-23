<?php

namespace App\Models;

use App\Support\AliasGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Character extends Model
{
    protected $table = 'character';

    protected $primaryKey = 'idcharacter';

    protected $fillable = ['name', 'image', 'game_idgame'];

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
}
