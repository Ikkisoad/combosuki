<?php

namespace Tests\Feature;

use App\Models\Button;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\ResourceValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterFlowChartTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_characters_combo_starters(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => '236B', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3, 'ignored' => true]);

        Combo::create([
            'combo' => '2LK > 236B', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => 1,
        ]);

        $response = $this->get(route('characters.tabs.flow-chart', [$game, $character]));

        $response->assertOk();
        $response->assertSee('combo-flow-chart', false);
        // Only the combo's opening move ships with the initial page — the
        // rest of the sequence is fetched on demand as the path is built
        // (see CharacterFlowChartNextTest), so a combo starter's later
        // moves are deliberately not expected in this response.
        $response->assertSee('2LK');
    }

    public function test_shows_no_data_message_when_the_character_has_no_combos(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $response = $this->get(route('characters.tabs.flow-chart', [$game, $character]));

        $response->assertOk();
        $response->assertSee('No combos match the current filters.');
    }

    public function test_the_type_filter_excludes_combos_of_a_different_type(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        $comboType = GameEntry::create(['gameid' => $game->idgame, 'title' => 'Combo', 'order' => 1]);
        $videoType = GameEntry::create(['gameid' => $game->idgame, 'title' => 'Combo Video', 'order' => 2]);

        Combo::create([
            'combo' => '2LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => $comboType->entryid,
        ]);

        $response = $this->get(route('characters.tabs.flow-chart', [$game, $character]).'?'.http_build_query(['listingtype' => $videoType->entryid]));

        $response->assertOk();
        $response->assertSee('No combos match the current filters.');
    }

    public function test_the_type_filter_includes_combos_of_the_matching_type(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        $comboType = GameEntry::create(['gameid' => $game->idgame, 'title' => 'Combo', 'order' => 1]);

        Combo::create([
            'combo' => '2LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => $comboType->entryid,
        ]);

        $response = $this->get(route('characters.tabs.flow-chart', [$game, $character]).'?'.http_build_query(['listingtype' => $comboType->entryid]));

        $response->assertOk();
        $response->assertSee('2LK');
    }

    public function test_a_primary_resource_filter_excludes_combos_with_a_different_value(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        $resource = GameResource::create(['game_idgame' => $game->idgame, 'text_name' => 'Where', 'type' => 1, 'primaryORsecundary' => 1]);
        $midscreen = ResourceValue::create(['value' => 'Midscreen', 'order' => 0, 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $corner = ResourceValue::create(['value' => 'Corner', 'order' => 1, 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $combo = Combo::create([
            'combo' => '2LK', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1,
        ]);
        $combo->resources()->create(['Resources_values_idResources_values' => $midscreen->idResources_values]);

        $response = $this->get(route('characters.tabs.flow-chart', [$game, $character]).'?'.http_build_query(['Where' => $corner->idResources_values]));

        $response->assertOk();
        $response->assertSee('No combos match the current filters.');
    }

    public function test_character_belonging_to_a_different_game_404s(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $otherGame->idgame]);

        $this->get(route('characters.tabs.flow-chart', [$game, $character]))->assertNotFound();
    }
}
