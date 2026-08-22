<?php

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Services\CombleGuessEvaluator;
use Tests\TestCase;

class CombleGuessEvaluatorTest extends TestCase
{
    /**
     * Regression test for a production-only bug (reported live on
     * combosuki.com/comble): a correct Type guess was marked wrong.
     *
     * game_entry.entryid is a BIGINT UNSIGNED primary key, while combo.type
     * is a plain signed INT. On real MySQL, PDO returns the former as a
     * string and the latter as a native int, so the evaluator's old strict
     * `===` compared "7" against 7 and always failed. SQLite's dynamic
     * typing returns both as PHP ints, which is why the feature-test suite
     * (SQLite-backed) never caught it. This test builds the models entirely
     * in memory with that exact string/int split, with no DB involved, so
     * it can't be masked by SQLite's typing again.
     */
    public function test_a_correct_type_guess_is_not_broken_by_a_string_vs_int_id_mismatch(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill(['idcharacter' => '9', 'name' => 'Ryu', 'game_idgame' => '5']);
        $character->exists = true;
        $character->setRelation('game', $game);

        $target = (new Combo())->forceFill([
            'idcombo' => 1,
            'combo' => 'AAA',
            'character_idcharacter' => '9',
            'type' => 7,
            'damage' => null,
        ]);
        $target->exists = true;
        $target->setRelation('character', $character);

        $guessedType = (new GameEntry())->forceFill(['entryid' => '7', 'title' => 'Combo', 'gameid' => 5]);

        $result = (new CombleGuessEvaluator())->evaluate($target, $game, $character, $guessedType, null);

        $this->assertTrue($result['type_correct']);
        $this->assertTrue($result['game_correct']);
        $this->assertTrue($result['character_correct']);
        $this->assertTrue($result['won']);
    }

    public function test_starter_guess_matches_the_first_six_raw_characters_case_insensitively(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ryu', 'game_idgame' => 5]);
        $character->exists = true;
        $character->setRelation('game', $game);

        $target = (new Combo())->forceFill([
            'idcombo' => 1,
            'combo' => '2LP 5MP 2HP',
            'character_idcharacter' => 9,
            'type' => 7,
            'damage' => null,
        ]);
        $target->exists = true;
        $target->setRelation('character', $character);

        $guessedType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 5]);
        $evaluator = new CombleGuessEvaluator();

        // '2LP 5MP 2HP'[0:6] === '2LP 5M', compared case-insensitively.
        $this->assertSame('correct', $evaluator->evaluate($target, $game, $character, $guessedType, null, '2lp 5m')['starter_result']);
        // Shares the first 4 positions ('2LP ') but not the last 2 — some characters right, not all.
        $this->assertSame('partial', $evaluator->evaluate($target, $game, $character, $guessedType, null, '2LP XZ')['starter_result']);
        // No position matches at all.
        $this->assertSame('wrong', $evaluator->evaluate($target, $game, $character, $guessedType, null, '999999')['starter_result']);
        $this->assertSame('wrong', $evaluator->evaluate($target, $game, $character, $guessedType, null, null)['starter_result']);
    }
}
