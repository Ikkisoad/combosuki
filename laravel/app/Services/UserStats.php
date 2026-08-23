<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\User;

class UserStats
{
    public function summary(User $user): array
    {
        $totalCombos = Combo::where('user_iduser', $user->iduser)->count();

        $topCharacterRow = Combo::where('user_iduser', $user->iduser)
            ->selectRaw('character_idcharacter, COUNT(*) as total')
            ->groupBy('character_idcharacter')
            ->orderByDesc('total')
            ->first();

        $topGameRow = Combo::query()
            ->join('character', 'character.idcharacter', '=', 'combo.character_idcharacter')
            ->where('combo.user_iduser', $user->iduser)
            ->selectRaw('character.game_idgame, COUNT(*) as total')
            ->groupBy('character.game_idgame')
            ->orderByDesc('total')
            ->first();

        return [
            'totalCombos' => $totalCombos,
            'mostSubmittedCharacter' => $topCharacterRow
                ? ['character' => Character::with('game')->find($topCharacterRow->character_idcharacter), 'count' => $topCharacterRow->total]
                : null,
            'mostSubmittedGame' => $topGameRow
                ? ['game' => Game::find($topGameRow->game_idgame), 'count' => $topGameRow->total]
                : null,
        ];
    }
}
