<?php

namespace Tests\Unit;

use App\Models\Button;
use App\Models\ButtonAlias;
use App\Models\Character;
use App\Models\CharacterButtonAlias;
use App\Models\Combo;
use App\Models\Game;
use App\Services\ComboFlowChartBuilder;
use App\Services\ComboNotationRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ComboFlowChartBuilderTest extends TestCase
{
    use RefreshDatabase;

    private ComboFlowChartBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new ComboFlowChartBuilder(new ComboNotationRenderer());
    }

    private function makeGame(): Game
    {
        return Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
    }

    /**
     * nextMoves()/matchingCombos() take an already-scoped combo collection
     * (the controller applies visibility and any Type/primary-resource
     * filters before calling in) rather than fetching it themselves, so
     * tests fetch it the same way the controller's unfiltered path does.
     */
    private function visibleCombos(Character $character): Collection
    {
        return $character->combos()->visibleTo(null)->get();
    }

    private function findMove(array $moves, string $key): ?array
    {
        foreach ($moves as $move) {
            if ($move['key'] === $key) {
                return $move;
            }
        }

        return null;
    }

    public function test_an_empty_path_returns_the_combos_starting_moves(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3, 'ignored' => true]);

        Combo::create(['combo' => '2LK > 5LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $moves = $this->builder->nextMoves($character, $this->visibleCombos($character), []);

        $this->assertNotNull($this->findMove($moves, '2lk'));
        $this->assertNull($this->findMove($moves, '5lk'));
    }

    public function test_a_move_that_actually_follows_the_path_is_offered_next(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3, 'ignored' => true]);

        Combo::create(['combo' => '2LK > 5LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $moves = $this->builder->nextMoves($character, $this->visibleCombos($character), ['2lk']);

        $move = $this->findMove($moves, '5lk');
        $this->assertNotNull($move);
        $this->assertSame(1, $move['count']);
    }

    public function test_the_same_continuation_across_multiple_combos_increments_the_count(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '5HK', 'color' => '#0000ff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3]);

        Combo::create(['combo' => '2LK 5LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);
        Combo::create(['combo' => '2LK 5LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);
        Combo::create(['combo' => '2LK 5HK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $starters = $this->builder->nextMoves($character, $this->visibleCombos($character), []);
        $this->assertSame(3, $this->findMove($starters, '2lk')['count']);

        $moves = $this->builder->nextMoves($character, $this->visibleCombos($character), ['2lk']);
        $this->assertSame(2, $this->findMove($moves, '5lk')['count']);
        $this->assertSame(1, $this->findMove($moves, '5hk')['count']);
    }

    /**
     * The core guarantee: two combos that happen to share one transition
     * ("5lk" -> "5hk") must not get stitched together into a suggestion
     * that matches neither of them. No real combo starts with
     * "2lk 5lk 5hk", so once a path has walked "2lk > 5lk", "5hk" must not
     * be offered next just because *some* combo somewhere goes from "5lk"
     * to "5hk" — only a combo whose sequence actually starts with the
     * *entire* path so far counts.
     */
    public function test_only_suggests_moves_from_combos_matching_the_entire_path_so_far(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '5HK', 'color' => '#0000ff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3]);

        Combo::create(['combo' => '2LK 5LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);
        Combo::create(['combo' => '5LK 5HK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $moves = $this->builder->nextMoves($character, $this->visibleCombos($character), ['2lk', '5lk']);

        $this->assertSame([], $moves);
    }

    public function test_reaching_the_end_of_every_matching_combo_returns_no_further_moves(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '5LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);

        Combo::create(['combo' => '5LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $moves = $this->builder->nextMoves($character, $this->visibleCombos($character), ['5lk']);

        $this->assertSame([], $moves);
    }

    public function test_ignored_button_is_excluded_from_move_boundaries_even_when_color_coded(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '>', 'color' => '#123456', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3, 'ignored' => true]);

        Combo::create(['combo' => '2LK > 5LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $moves = $this->builder->nextMoves($character, $this->visibleCombos($character), ['2lk']);

        $this->assertNotNull($this->findMove($moves, '5lk'));
        $this->assertNull($this->findMove($moves, '>'));
    }

    public function test_aliases_are_resolved_before_computing_next_moves(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Toph', 'game_idgame' => $game->idgame]);
        $tackleButton = Button::create(['name' => '236A', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5H', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3, 'ignored' => true]);
        CharacterButtonAlias::create(['alias' => 'Tackle', 'button_idbutton' => $tackleButton->idbutton, 'character_idcharacter' => $character->idcharacter]);

        Combo::create(['combo' => 'Tackle > 5H', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $starters = $this->builder->nextMoves($character, $this->visibleCombos($character), []);
        $this->assertNotNull($this->findMove($starters, '236a'));

        $moves = $this->builder->nextMoves($character, $this->visibleCombos($character), ['236a']);
        $this->assertNotNull($this->findMove($moves, '5h'));
    }

    public function test_only_visible_combos_are_considered(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $author = \App\Models\User::create(['nickname' => 'someone', 'password' => 'password123']);
        Button::create(['name' => '5LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);

        // Unverified combo by a user with no other verified combo: hidden from guests.
        Combo::create([
            'combo' => '5LK', 'character_idcharacter' => $character->idcharacter, 'user_iduser' => $author->iduser,
            'submited' => now(), 'damage' => 100, 'type' => 1, 'verified' => 0,
        ]);

        $moves = $this->builder->nextMoves($character, $this->visibleCombos($character), []);

        $this->assertSame([], $moves);
    }

    /**
     * Regression test: a game whose admin never got around to color-coding
     * any button (every Button row still at the renderer's default color,
     * so tokenize() classifies every word as plain text) used to produce no
     * moves at all — move detection was keyed entirely off the "colored"
     * classification. It should instead fall back to one move per
     * ">"-separated segment.
     */
    public function test_falls_back_to_ignored_button_boundaries_when_no_button_is_color_coded(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => 'LK', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => 'MK', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '2', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3]);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 4, 'ignored' => true]);

        Combo::create(['combo' => '2 LK > 2 MK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $starters = $this->builder->nextMoves($character, $this->visibleCombos($character), []);
        $this->assertNotNull($this->findMove($starters, '2 lk'));

        $moves = $this->builder->nextMoves($character, $this->visibleCombos($character), ['2 lk']);
        $this->assertNotNull($this->findMove($moves, '2 mk'));
    }

    /**
     * Regression test: PHP silently casts a purely-numeric array key (e.g.
     * "9") to an int. A move whose whole label is just digits — a bare "9"
     * jump input written on its own between separators, which happens on
     * games without color-coding — used to come back out as an int and
     * get json_encoded as a JSON number instead of a string, breaking the
     * client's id matching against it.
     */
    public function test_a_purely_numeric_move_key_stays_a_string(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => 'HP', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '9', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3, 'ignored' => true]);

        Combo::create(['combo' => '9 > HP', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $starters = $this->builder->nextMoves($character, $this->visibleCombos($character), []);
        $this->assertIsString($this->findMove($starters, '9')['key']);

        $moves = $this->builder->nextMoves($character, $this->visibleCombos($character), ['9']);
        $this->assertIsString($this->findMove($moves, 'hp')['key']);
    }

    public function test_moves_for_combo_returns_the_ordered_move_list_for_one_combo(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3, 'ignored' => true]);

        $combo = Combo::create(['combo' => '2LK > 5LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $moves = $this->builder->movesForCombo($combo);

        $this->assertSame(
            [
                ['key' => '2lk', 'label' => '2LK', 'color' => '#ff0000'],
                ['key' => '5lk', 'label' => '5LK', 'color' => '#00ff00'],
            ],
            $moves
        );
    }

    public function test_matching_combos_returns_combos_whose_sequence_starts_with_the_path(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '5HK', 'color' => '#0000ff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3]);

        $matching = Combo::create(['combo' => '2LK 5LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);
        Combo::create(['combo' => '2LK 5HK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $matches = $this->builder->matchingCombos($character, $this->visibleCombos($character), ['2lk', '5lk']);

        $this->assertCount(1, $matches);
        $this->assertSame($matching->idcombo, $matches->first()->idcombo);
    }

    public function test_matching_combos_matches_a_prefix_of_a_longer_combo(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '5HK', 'color' => '#0000ff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3]);

        $combo = Combo::create(['combo' => '2LK 5LK 5HK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $matches = $this->builder->matchingCombos($character, $this->visibleCombos($character), ['2lk']);

        $this->assertCount(1, $matches);
        $this->assertSame($combo->idcombo, $matches->first()->idcombo);
    }

    public function test_matching_combos_excludes_a_combo_shorter_than_the_path(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);

        Combo::create(['combo' => '2LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $matches = $this->builder->matchingCombos($character, $this->visibleCombos($character), ['2lk', '5lk']);

        $this->assertCount(0, $matches);
    }

    public function test_matching_combos_returns_empty_for_an_empty_path(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);

        Combo::create(['combo' => '2LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $matches = $this->builder->matchingCombos($character, $this->visibleCombos($character), []);

        $this->assertCount(0, $matches);
    }

    public function test_matching_combos_stitched_across_different_source_combos_finds_nothing_but_does_not_error(): void
    {
        $game = $this->makeGame();
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '5HK', 'color' => '#0000ff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3]);

        // "5lk" -> "5hk" is a real transition (second combo), but no combo
        // actually starts with "2lk 5lk 5hk" — this stitches a prefix from
        // the first combo with a transition that only occurs elsewhere.
        Combo::create(['combo' => '2LK 5LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);
        Combo::create(['combo' => '5LK 5HK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $matches = $this->builder->matchingCombos($character, $this->visibleCombos($character), ['2lk', '5lk', '5hk']);

        $this->assertCount(0, $matches);
    }
}
