<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboNotCommentsFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test: `comments NOT LIKE '%word%'` alone excludes a combo
     * whose comments column is NULL, because SQL's NOT LIKE against NULL
     * evaluates to NULL (neither true nor false) rather than true — even
     * though a combo with no comments obviously doesn't mention the excluded
     * word. This silently hid every commentless combo from any search or
     * default-query match using a "doesn't mention" filter (see
     * FiltersCombos::applyFilters()).
     */
    public function test_a_combo_with_no_comments_matches_a_does_not_mention_filter(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);

        $combo = Combo::create([
            'combo' => '2A > 5B', 'character_idcharacter' => $character->idcharacter, 'damage' => 100, 'type' => 1, 'comments' => null,
        ]);

        $response = $this->get(route('games.combos.index', $game).'?search=1&notcomments=corner');

        $response->assertOk();
        $response->assertSee($combo->combo);
    }

    public function test_a_combo_whose_comments_mention_the_excluded_word_is_still_excluded(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);

        $combo = Combo::create([
            'combo' => '2A > 5B', 'character_idcharacter' => $character->idcharacter, 'damage' => 100, 'type' => 1, 'comments' => 'only in the corner',
        ]);

        $response = $this->get(route('games.combos.index', $game).'?search=1&notcomments=corner');

        $response->assertOk();
        $response->assertDontSee($combo->combo);
    }
}
