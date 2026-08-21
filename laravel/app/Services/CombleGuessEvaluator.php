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
     *
     * IDs are cast to int before comparing: game_entry.entryid (and other
     * PKs) are BIGINT UNSIGNED, while combo.type is a plain signed INT — on
     * real MySQL, PDO returns those as a string and a native int
     * respectively, so a strict === here would wrongly mark a correct guess
     * as wrong. SQLite's dynamic typing hides this, which is why it only
     * ever surfaces against the production database.
     */
    public function evaluate(Combo $target, Game $guessedGame, Character $guessedCharacter, GameEntry $guessedType, ?float $guessedDamage): array
    {
        $gameCorrect = (int) $guessedGame->idgame === (int) $target->character->game_idgame;
        $characterCorrect = (int) $guessedCharacter->idcharacter === (int) $target->character_idcharacter;
        $typeCorrect = (int) $guessedType->entryid === (int) $target->type;

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
