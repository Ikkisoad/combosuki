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
    public function evaluate(Combo $target, Game $guessedGame, Character $guessedCharacter, GameEntry $guessedType, ?float $guessedDamage, ?string $guessedStarter = null): array
    {
        $gameCorrect = (int) $guessedGame->idgame === (int) $target->character->game_idgame;
        $characterCorrect = (int) $guessedCharacter->idcharacter === (int) $target->character_idcharacter;
        $typeCorrect = (int) $guessedType->entryid === (int) $target->type;

        return [
            'game_correct' => $gameCorrect,
            'character_correct' => $characterCorrect,
            'type_correct' => $typeCorrect,
            'starter_result' => $this->starterResult($target, $guessedStarter),
            'damage_hint' => $this->damageHint($target, $guessedDamage),
            'won' => $gameCorrect && $characterCorrect,
        ];
    }

    /**
     * Compares a guess at the combo's opening 6 characters against the real
     * notation string (not tokens — literal characters, spaces included),
     * position by position, case-insensitively. Never guessed, never gates a
     * win: same non-blocking "bonus hint" role as damage.
     *
     * Returns 'correct' (identical, same length), 'partial' (at least one
     * character right in its own position, but not a full match — shown as
     * orange rather than plain right/wrong), or 'wrong' (no positions
     * match, or nothing was guessed).
     */
    private function starterResult(Combo $target, ?string $guessedStarter): string
    {
        if ($guessedStarter === null || $guessedStarter === '') {
            return 'wrong';
        }

        $guessed = mb_strtolower($guessedStarter);
        $actual = mb_strtolower(mb_substr($target->combo, 0, 6));

        if ($guessed === $actual) {
            return 'correct';
        }

        $length = min(mb_strlen($guessed), mb_strlen($actual));

        for ($i = 0; $i < $length; $i++) {
            if (mb_substr($guessed, $i, 1) === mb_substr($actual, $i, 1)) {
                return 'partial';
            }
        }

        return 'wrong';
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
