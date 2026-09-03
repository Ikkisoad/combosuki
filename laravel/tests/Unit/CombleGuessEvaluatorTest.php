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

    public function test_starter_guess_matches_the_first_six_characters_with_spaces_stripped_case_insensitively(): void
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

        // '2LP 5MP 2HP' with spaces stripped is '2LP5MP2HP'[0:6] === '2LP5MP', compared case-insensitively.
        $correct = $evaluator->evaluate($target, $game, $character, $guessedType, null, '2lp5mp');
        $this->assertSame('correct', $correct['starter_result']);
        $this->assertSame(6, $correct['starter_match_count']);
        $this->assertSame(6, $correct['starter_total']);

        // Spaces in the guess itself are stripped too, so they don't throw off the position comparison.
        $this->assertSame('correct', $evaluator->evaluate($target, $game, $character, $guessedType, null, '2l p 5mp')['starter_result']);

        // Shares the first 4 positions ('2LP5') but not the last 2 — some characters right, not all.
        $partial = $evaluator->evaluate($target, $game, $character, $guessedType, null, '2lp5xz');
        $this->assertSame('partial', $partial['starter_result']);
        $this->assertSame(4, $partial['starter_match_count']);
        $this->assertSame(6, $partial['starter_total']);

        // No position matches at all.
        $wrong = $evaluator->evaluate($target, $game, $character, $guessedType, null, '999999');
        $this->assertSame('wrong', $wrong['starter_result']);
        $this->assertSame(0, $wrong['starter_match_count']);

        $this->assertSame('wrong', $evaluator->evaluate($target, $game, $character, $guessedType, null, null)['starter_result']);
    }

    /**
     * The starter cell's background is a red→yellow→green ramp scaled by
     * how many of the 6 characters landed right, not a flat color for every
     * non-exact guess — see CombleGuessEvaluator::starterColor(). Hue 0 is
     * red (0 matches), hue 120 is green (a full match), and the guess
     * shares the same total (6) as the "matches the first six characters"
     * test above, so the intermediate points land on tidy hue values.
     */
    public function test_starter_color_scales_from_red_to_green_with_the_match_count(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ryu', 'game_idgame' => 5]);
        $character->exists = true;
        $character->setRelation('game', $game);

        $target = (new Combo())->forceFill([
            'idcombo' => 1,
            'combo' => '2LP5MP',
            'character_idcharacter' => 9,
            'type' => 7,
            'damage' => null,
        ]);
        $target->exists = true;
        $target->setRelation('character', $character);

        $guessedType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 5]);
        $evaluator = new CombleGuessEvaluator();

        // 0 of 6 matched — hue 0, red.
        $this->assertSame('hsl(0, 75%, 38%)', $evaluator->evaluate($target, $game, $character, $guessedType, null, 'zzzzzz')['starter_color']);
        // 3 of 6 matched — hue 60, the red-green midpoint (yellow).
        $this->assertSame('hsl(60, 75%, 38%)', $evaluator->evaluate($target, $game, $character, $guessedType, null, '2lpzzz')['starter_color']);
        // 6 of 6 matched — hue 120, green.
        $this->assertSame('hsl(120, 75%, 38%)', $evaluator->evaluate($target, $game, $character, $guessedType, null, '2lp5mp')['starter_color']);
        // Never guessed — same as 0 matches, red.
        $this->assertSame('hsl(0, 75%, 38%)', $evaluator->evaluate($target, $game, $character, $guessedType, null, null)['starter_color']);
    }

    /**
     * game_entry rows are per-game, so "Combo" in one game and "Combo" in
     * another are different entryids entirely — but a guess titled the same
     * as the target's type should still count as correct, since the
     * category itself is what's meaningful, not which game's specific row
     * it happens to be. A different title (even for the *same* entryid,
     * which can't really happen but exercises the "no accidental match"
     * path) must still be wrong.
     */
    public function test_type_correctness_matches_by_title_even_across_different_entryids(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ryu', 'game_idgame' => 5]);
        $character->exists = true;
        $character->setRelation('game', $game);

        $targetType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 5]);

        $target = (new Combo())->forceFill([
            'idcombo' => 1,
            'combo' => 'AAA',
            'character_idcharacter' => 9,
            'type' => 7,
            'damage' => null,
        ]);
        $target->exists = true;
        $target->setRelation('character', $character);
        $target->setRelation('listingType', $targetType);

        $evaluator = new CombleGuessEvaluator();

        // Different game, different entryid, same title (case/whitespace
        // insensitively) — still correct.
        $sameTitleDifferentEntry = (new GameEntry())->forceFill(['entryid' => 42, 'title' => ' combo ', 'gameid' => 99]);
        $this->assertTrue($evaluator->evaluate($target, $game, $character, $sameTitleDifferentEntry, null)['type_correct']);

        // Different entryid, different title — genuinely wrong.
        $differentTitle = (new GameEntry())->forceFill(['entryid' => 43, 'title' => 'Okizeme', 'gameid' => 99]);
        $this->assertFalse($evaluator->evaluate($target, $game, $character, $differentTitle, null)['type_correct']);
    }

    public function test_empty_string_starter_guess_is_wrong_distinct_from_a_null_guess(): void
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

        $this->assertSame('wrong', $evaluator->evaluate($target, $game, $character, $guessedType, null, '')['starter_result']);
    }

    public function test_damage_hint_is_unknown_when_either_side_is_missing_damage(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ryu', 'game_idgame' => 5]);
        $character->exists = true;
        $character->setRelation('game', $game);

        $guessedType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 5]);
        $evaluator = new CombleGuessEvaluator();

        $targetWithoutDamage = (new Combo())->forceFill([
            'idcombo' => 1, 'combo' => 'AAA', 'character_idcharacter' => 9, 'type' => 7, 'damage' => null,
        ]);
        $targetWithoutDamage->exists = true;
        $targetWithoutDamage->setRelation('character', $character);

        $this->assertSame('unknown', $evaluator->evaluate($targetWithoutDamage, $game, $character, $guessedType, 500.0)['damage_hint']);

        $targetWithDamage = (new Combo())->forceFill([
            'idcombo' => 2, 'combo' => 'AAA', 'character_idcharacter' => 9, 'type' => 7, 'damage' => 500,
        ]);
        $targetWithDamage->exists = true;
        $targetWithDamage->setRelation('character', $character);

        $this->assertSame('unknown', $evaluator->evaluate($targetWithDamage, $game, $character, $guessedType, null)['damage_hint']);
    }

    /**
     * abs($diff) < 0.01 is the "equal" boundary. A diff of exactly 0.01 (or
     * anything at/over it) must NOT be treated as equal.
     */
    public function test_damage_hint_epsilon_boundary_is_exclusive(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ryu', 'game_idgame' => 5]);
        $character->exists = true;
        $character->setRelation('game', $game);

        $guessedType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 5]);
        $evaluator = new CombleGuessEvaluator();

        $target = (new Combo())->forceFill([
            'idcombo' => 1, 'combo' => 'AAA', 'character_idcharacter' => 9, 'type' => 7, 'damage' => 500.0,
        ]);
        $target->exists = true;
        $target->setRelation('character', $character);

        // Just under the 0.01 threshold: still counted as equal.
        $this->assertSame('equal', $evaluator->evaluate($target, $game, $character, $guessedType, 500.005)['damage_hint']);

        // Clearly over the 0.01 threshold (kept away from the exact boundary to avoid float
        // rounding noise): no longer equal, direction reflects the sign of the diff. Both are
        // a tiny fraction of a percent off, so closeness is "close".
        $this->assertSame('lower_close', $evaluator->evaluate($target, $game, $character, $guessedType, 500.02)['damage_hint']);
        $this->assertSame('higher_close', $evaluator->evaluate($target, $game, $character, $guessedType, 499.98)['damage_hint']);
    }

    /**
     * The damage hint's direction ("higher"/"lower") is paired with a
     * closeness tier ("close"/"far") so the player can tell a near miss from
     * a wild guess. Closeness is a ratio (bigger value / smaller value), not
     * a percentage of the actual value — combo damage in the real dataset
     * spans from double digits to over a million, so a fixed percentage of
     * the actual value is either impossibly tight for a small combo or
     * trivially wide for a huge one. "Within 50% either way" (ratio <= 1.5)
     * is "close" (one arrow); anything wider is "far" (double arrow).
     */
    public function test_damage_hint_closeness_reflects_ratio_distance_from_the_actual_value(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ryu', 'game_idgame' => 5]);
        $character->exists = true;
        $character->setRelation('game', $game);

        $guessedType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 5]);
        $evaluator = new CombleGuessEvaluator();

        $target = (new Combo())->forceFill([
            'idcombo' => 1, 'combo' => 'AAA', 'character_idcharacter' => 9, 'type' => 7, 'damage' => 1200.0,
        ]);
        $target->exists = true;
        $target->setRelation('character', $character);

        // Exactly at the 1.5x boundary (1200 / 800 = 1.5, 1800 / 1200 = 1.5): still "close".
        $this->assertSame('higher_close', $evaluator->evaluate($target, $game, $character, $guessedType, 800.0)['damage_hint']);
        $this->assertSame('lower_close', $evaluator->evaluate($target, $game, $character, $guessedType, 1800.0)['damage_hint']);

        // Just past the boundary: "far".
        $this->assertSame('higher_far', $evaluator->evaluate($target, $game, $character, $guessedType, 799.0)['damage_hint']);
        $this->assertSame('lower_far', $evaluator->evaluate($target, $game, $character, $guessedType, 1801.0)['damage_hint']);

        // A wildly wrong guess is still just "far", not some further tier.
        $this->assertSame('higher_far', $evaluator->evaluate($target, $game, $character, $guessedType, 10.0)['damage_hint']);
    }

    /**
     * The ratio metric must behave the same regardless of the actual
     * value's magnitude — a small combo (tens of damage) shouldn't need a
     * tighter absolute guess than a huge one (hundreds of thousands) to
     * count as "close".
     */
    public function test_damage_hint_closeness_ratio_is_consistent_across_wildly_different_magnitudes(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ryu', 'game_idgame' => 5]);
        $character->exists = true;
        $character->setRelation('game', $game);

        $guessedType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 5]);
        $evaluator = new CombleGuessEvaluator();

        // A tiny combo: guessing 24 for an actual of 18 is a ratio of
        // 24/18 = 1.33 — comfortably "close" despite the small absolute gap.
        $tinyTarget = (new Combo())->forceFill([
            'idcombo' => 1, 'combo' => 'AAA', 'character_idcharacter' => 9, 'type' => 7, 'damage' => 18.0,
        ]);
        $tinyTarget->exists = true;
        $tinyTarget->setRelation('character', $character);

        $this->assertSame('lower_close', $evaluator->evaluate($tinyTarget, $game, $character, $guessedType, 24.0)['damage_hint']);

        // A huge combo: guessing 1,000,000 for an actual of 1,500,000 is the
        // same 1.5 ratio — "close" for exactly the same reason, even though
        // the absolute gap (500,000) is far bigger than the tiny combo's
        // entire damage value.
        $hugeTarget = (new Combo())->forceFill([
            'idcombo' => 2, 'combo' => 'AAA', 'character_idcharacter' => 9, 'type' => 7, 'damage' => 1500000.0,
        ]);
        $hugeTarget->exists = true;
        $hugeTarget->setRelation('character', $character);

        $this->assertSame('higher_close', $evaluator->evaluate($hugeTarget, $game, $character, $guessedType, 1000000.0)['damage_hint']);
    }

    /**
     * A target damage of 0 makes a relative-error ratio meaningless (any
     * nonzero guess is "infinitely" far off by that measure), so closeness
     * falls back to an absolute cutoff instead.
     */
    public function test_damage_hint_closeness_falls_back_to_an_absolute_cutoff_when_actual_damage_is_zero(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ryu', 'game_idgame' => 5]);
        $character->exists = true;
        $character->setRelation('game', $game);

        $guessedType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 5]);
        $evaluator = new CombleGuessEvaluator();

        $target = (new Combo())->forceFill([
            'idcombo' => 1, 'combo' => 'AAA', 'character_idcharacter' => 9, 'type' => 7, 'damage' => 0.0,
        ]);
        $target->exists = true;
        $target->setRelation('character', $character);

        $this->assertSame('lower_close', $evaluator->evaluate($target, $game, $character, $guessedType, 200.0)['damage_hint']);
        $this->assertSame('lower_far', $evaluator->evaluate($target, $game, $character, $guessedType, 800.0)['damage_hint']);
    }

    public function test_type_guess_correct_by_id_alone_even_when_title_and_game_differ(): void
    {
        $game = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ryu', 'game_idgame' => 5]);
        $character->exists = true;
        $character->setRelation('game', $game);

        // The target's own listingType relation is deliberately different in title from the
        // guessed GameEntry, so a title-based match would fail — only the entryid should matter.
        $targetType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 5]);

        $target = (new Combo())->forceFill([
            'idcombo' => 1, 'combo' => 'AAA', 'character_idcharacter' => 9, 'type' => 7, 'damage' => null,
        ]);
        $target->exists = true;
        $target->setRelation('character', $character);
        $target->setRelation('listingType', $targetType);

        // Same entryid (7) as the target, but a different title and a different game entirely.
        $sameIdDifferentTitle = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Okizeme', 'gameid' => 99]);

        $evaluator = new CombleGuessEvaluator();

        $this->assertTrue($evaluator->evaluate($target, $game, $character, $sameIdDifferentTitle, null)['type_correct']);
    }

    /**
     * gameCorrect and characterCorrect share the exact same int-cast comparison
     * pattern as the already-regression-tested type_correct field (see the
     * first test in this class) — extend the same string/int PDO-type split to
     * character_idcharacter and idgame so a future refactor can't silently drop
     * the cast on just one of the three fields.
     */
    public function test_game_and_character_correctness_are_not_broken_by_a_string_vs_int_id_mismatch(): void
    {
        $game = (new Game())->forceFill(['idgame' => '5', 'name' => 'Test Game']);
        $game->exists = true;

        $character = (new Character())->forceFill(['idcharacter' => '9', 'name' => 'Ryu', 'game_idgame' => 5]);
        $character->exists = true;
        $character->setRelation('game', $game);

        $target = (new Combo())->forceFill([
            'idcombo' => 1,
            'combo' => 'AAA',
            'character_idcharacter' => 9,
            'type' => 7,
            'damage' => null,
        ]);
        $target->exists = true;
        $target->setRelation('character', (new Character())->forceFill(['idcharacter' => 9, 'game_idgame' => '5']));

        $guessedGame = (new Game())->forceFill(['idgame' => 5, 'name' => 'Test Game']);
        $guessedGame->exists = true;
        $guessedCharacter = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ryu', 'game_idgame' => 5]);
        $guessedCharacter->exists = true;

        $guessedType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 5]);

        $result = (new CombleGuessEvaluator())->evaluate($target, $guessedGame, $guessedCharacter, $guessedType, null);

        $this->assertTrue($result['game_correct']);
        $this->assertTrue($result['character_correct']);
    }

    /**
     * A wrong game/character guess still gets a visual "close" hint (orange,
     * like the starter column) when the name is clearly related to the
     * answer — e.g. guessing "Street Fighter V" for "Street Fighter 6", or
     * guessing "Ibuki" from the wrong game when the answer is also Ibuki.
     * game_correct/character_correct (the win-gating booleans) must stay
     * false either way — only the display-only *_result fields go orange.
     */
    public function test_a_similar_but_wrong_game_or_character_name_is_marked_partial(): void
    {
        $targetGame = (new Game())->forceFill(['idgame' => 5, 'name' => 'Street Fighter 6']);
        $targetGame->exists = true;

        $targetCharacter = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ibuki', 'game_idgame' => 5]);
        $targetCharacter->exists = true;
        $targetCharacter->setRelation('game', $targetGame);

        $target = (new Combo())->forceFill([
            'idcombo' => 1, 'combo' => 'AAA', 'character_idcharacter' => 9, 'type' => 7, 'damage' => null,
        ]);
        $target->exists = true;
        $target->setRelation('character', $targetCharacter);

        $guessedGame = (new Game())->forceFill(['idgame' => 2, 'name' => 'Street Fighter V']);
        $guessedGame->exists = true;

        // Same name as the target's character, but a different game/id — a
        // different combo row entirely, not the answer itself.
        $guessedCharacter = (new Character())->forceFill(['idcharacter' => 3, 'name' => 'Ibuki', 'game_idgame' => 2]);
        $guessedCharacter->exists = true;

        $guessedType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 2]);

        $result = (new CombleGuessEvaluator())->evaluate($target, $guessedGame, $guessedCharacter, $guessedType, null);

        $this->assertFalse($result['game_correct']);
        $this->assertSame('partial', $result['game_result']);
        $this->assertFalse($result['character_correct']);
        $this->assertSame('partial', $result['character_result']);
        $this->assertFalse($result['won']);
    }

    /**
     * Sanity check for the other side of the same feature: two names that
     * share no meaningful words (only maybe a filtered-out connector like
     * "the") must stay plain "wrong", not partial.
     */
    public function test_an_unrelated_game_or_character_name_is_marked_wrong_not_partial(): void
    {
        $targetGame = (new Game())->forceFill(['idgame' => 5, 'name' => 'Street Fighter 6']);
        $targetGame->exists = true;

        $targetCharacter = (new Character())->forceFill(['idcharacter' => 9, 'name' => 'Ibuki', 'game_idgame' => 5]);
        $targetCharacter->exists = true;
        $targetCharacter->setRelation('game', $targetGame);

        $target = (new Combo())->forceFill([
            'idcombo' => 1, 'combo' => 'AAA', 'character_idcharacter' => 9, 'type' => 7, 'damage' => null,
        ]);
        $target->exists = true;
        $target->setRelation('character', $targetCharacter);

        $guessedGame = (new Game())->forceFill(['idgame' => 2, 'name' => 'Guilty Gear -Strive-']);
        $guessedGame->exists = true;

        $guessedCharacter = (new Character())->forceFill(['idcharacter' => 3, 'name' => 'Sol Badguy', 'game_idgame' => 2]);
        $guessedCharacter->exists = true;

        $guessedType = (new GameEntry())->forceFill(['entryid' => 7, 'title' => 'Combo', 'gameid' => 2]);

        $result = (new CombleGuessEvaluator())->evaluate($target, $guessedGame, $guessedCharacter, $guessedType, null);

        $this->assertSame('wrong', $result['game_result']);
        $this->assertSame('wrong', $result['character_result']);
    }
}
