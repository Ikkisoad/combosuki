<?php

namespace Tests\Feature;

use App\Models\Button;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterFlowChartNextTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_move_that_follows_the_given_path(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '236B', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3, 'ignored' => true]);

        Combo::create([
            'combo' => '2LK > 236B', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => 1,
        ]);

        $response = $this->getJson(route('characters.tabs.flow-chart.next', [$game, $character]).'?'.http_build_query(['path' => ['2lk']]));

        $response->assertOk();
        $response->assertJson(['moves' => [['key' => '236b', 'label' => '236B', 'color' => '#00ff00', 'count' => 1]]]);
    }

    /**
     * The exact behavior requested: a move that only ever followed the
     * current one in a *different* combo must not be suggested — only
     * continuations of combos whose sequence actually matches the whole
     * path built so far.
     */
    public function test_does_not_suggest_a_move_stitched_from_an_unrelated_combo(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '5HK', 'color' => '#0000ff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3]);

        Combo::create(['combo' => '2LK 5LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);
        Combo::create(['combo' => '5LK 5HK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1]);

        $response = $this->getJson(route('characters.tabs.flow-chart.next', [$game, $character]).'?'.http_build_query(['path' => ['2lk', '5lk']]));

        $response->assertOk();
        $response->assertExactJson(['moves' => []]);
    }

    public function test_the_type_filter_narrows_which_combos_next_moves_are_drawn_from(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        $comboType = GameEntry::create(['gameid' => $game->idgame, 'title' => 'Combo', 'order' => 1]);
        $videoType = GameEntry::create(['gameid' => $game->idgame, 'title' => 'Combo Video', 'order' => 2]);

        Combo::create([
            'combo' => '2LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => $comboType->entryid,
        ]);

        $response = $this->getJson(route('characters.tabs.flow-chart.next', [$game, $character]).'?'.http_build_query(['listingtype' => $videoType->entryid]));

        $response->assertOk();
        $response->assertExactJson(['moves' => []]);
    }
}
