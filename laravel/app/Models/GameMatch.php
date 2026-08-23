<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMatch extends Model
{
    protected $table = 'matches';

    protected $primaryKey = 'idmatch';

    protected $fillable = [
        'game_idgame',
        'player_one', 'player_one_user_iduser', 'player_one_character_idcharacter',
        'player_two', 'player_two_user_iduser', 'player_two_character_idcharacter',
        'video', 'played_at', 'user_iduser',
    ];

    protected function casts(): array
    {
        return [
            'played_at' => 'date',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_idgame');
    }

    public function playerOneCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'player_one_character_idcharacter');
    }

    public function playerTwoCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'player_two_character_idcharacter');
    }

    public function playerOneUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_one_user_iduser');
    }

    public function playerTwoUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_two_user_iduser');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_iduser');
    }
}
