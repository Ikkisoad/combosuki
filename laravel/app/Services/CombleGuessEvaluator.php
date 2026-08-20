<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;

class CombleGuessEvaluator
{
    /**
     * Compares one guess against the day's target combo. Only game/character
     * correctness determines a win; type and damage are hint-only columns.
     */
    public function evaluate(Combo $target, Game $guessedGame, Character $guessedCharacter, GameEntry $guessedType, ?float $guessedDamage): array
    {
        $gameCorrect = $guessedGame->idgame === $target->character->game_idgame;
        $characterCorrect = $guessedCharacter->idcharacter === $target->character_idcharacter;
        $typeCorrect = $guessedType->entryid === $target->type;

        return [
            'game_correct' => $gameCorrect,
            'character_correct' => $characterCorrect,
            'type_correct' => $typeCorrect,
            'damage_hint' => $this->damageHint($target, $guessedDamage),
            'won' => $gameCorrect && $characterCorrect,
        ];
    }

    private function damageHint(Combo $target, ?float $guessedDamage): string
    {
        if ($target->damage === null || $guessedDamage === null) {
            return 'unknown';
        }

        $diff = (float) $target->damage - $guessedDamage;

        if (abs($diff) < 0.01) {
            return 'equal';
        }

        return $diff > 0 ? 'higher' : 'lower';
    }
}
