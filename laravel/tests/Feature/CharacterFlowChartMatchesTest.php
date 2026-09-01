<?php

namespace Tests\Feature;

use App\Models\Button;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterFlowChartMatchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_combos_that_start_with_the_given_path(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '236B', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3, 'ignored' => true]);

        $combo = Combo::create([
            'combo' => '2LK > 236B', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => 1,
        ]);

        $response = $this->get(route('characters.tabs.flow-chart.matches', [$game, $character]).'?'.http_build_query(['path' => ['2lk', '236b']]));

        $response->assertOk();
        $response->assertSee(route('combos.show', $combo), false);
    }

    public function test_shows_a_reassuring_message_when_no_combo_matches_the_path(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);

        Combo::create([
            'combo' => '2LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => 1,
        ]);

        $response = $this->get(route('characters.tabs.flow-chart.matches', [$game, $character]).'?'.http_build_query(['path' => ['2lk', '236b']]));

        $response->assertOk();
        $response->assertSee('No existing combo matches this path yet', false);
        $response->assertSee(route('games.combos.create', $game), false);
    }

    public function test_a_combo_matching_the_path_but_not_the_active_filter_is_excluded(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        $comboType = GameEntry::create(['gameid' => $game->idgame, 'title' => 'Combo', 'order' => 1]);
        $videoType = GameEntry::create(['gameid' => $game->idgame, 'title' => 'Combo Video', 'order' => 2]);

        $combo = Combo::create([
            'combo' => '2LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => $comboType->entryid,
        ]);

        $response = $this->get(route('characters.tabs.flow-chart.matches', [$game, $character]).'?'.http_build_query([
            'path' => ['2lk'],
            'listingtype' => $videoType->entryid,
        ]));

        $response->assertOk();
        $response->assertDontSee(route('combos.show', $combo), false);
    }
}
