<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameResource;
use App\Models\ResourceValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameRandomizerTopComboTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_highest_damage_combo_matching_the_rolled_character_and_resource(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $resource = GameResource::create([
            'game_idgame' => $game->idgame, 'text_name' => 'Where?', 'type' => 1, 'primaryORsecundary' => 1,
        ]);
        $corner = ResourceValue::create(['value' => 'Corner', 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $midscreen = ResourceValue::create(['value' => 'Midscreen', 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $weaker = Combo::create([
            'combo' => '2LK > 5C', 'character_idcharacter' => $character->idcharacter, 'damage' => 100, 'type' => 1,
        ]);
        $weaker->resources()->create(['Resources_values_idResources_values' => $corner->idResources_values]);

        $stronger = Combo::create([
            'combo' => '2LK > 236B', 'character_idcharacter' => $character->idcharacter, 'damage' => 300, 'type' => 1,
        ]);
        $stronger->resources()->create(['Resources_values_idResources_values' => $corner->idResources_values]);

        $wrongResource = Combo::create([
            'combo' => '2LK > 623C', 'character_idcharacter' => $character->idcharacter, 'damage' => 500, 'type' => 1,
        ]);
        $wrongResource->resources()->create(['Resources_values_idResources_values' => $midscreen->idResources_values]);

        $params = http_build_query([
            'character_idcharacter' => $character->idcharacter,
            'resources' => json_encode([$resource->idgame_resources => $corner->idResources_values]),
        ]);

        $response = $this->get(route('games.tabs.randomizer-top-combo', $game).'?'.$params);

        $response->assertOk();
        $response->assertSee('Top combo for this roll');
        $response->assertSee('2LK &gt; 236B', false);
        $response->assertDontSee('2LK &gt; 5C', false);
        $response->assertDontSee('2LK &gt; 623C', false);
    }

    public function test_shows_be_the_first_message_when_no_combo_matches_the_roll(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Painwheel', 'game_idgame' => $game->idgame]);

        $response = $this->get(route('games.tabs.randomizer-top-combo', $game).'?character_idcharacter='.$character->idcharacter);

        $response->assertOk();
        $response->assertSee('No combo submitted for this roll yet');
    }

    public function test_number_resource_roll_matches_only_the_exact_rolled_value(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Cerebella', 'game_idgame' => $game->idgame]);

        $resource = GameResource::create([
            'game_idgame' => $game->idgame, 'text_name' => 'Meter', 'type' => 2, 'primaryORsecundary' => 1,
        ]);
        $resourceValue = ResourceValue::create(['value' => 'Bar', 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $matching = Combo::create([
            'combo' => '2A > 5C', 'character_idcharacter' => $character->idcharacter, 'damage' => 200, 'type' => 1,
        ]);
        $matching->resources()->create([
            'Resources_values_idResources_values' => $resourceValue->idResources_values, 'number_value' => 2,
        ]);

        $higherDamageButWrongValue = Combo::create([
            'combo' => '2A > 236B', 'character_idcharacter' => $character->idcharacter, 'damage' => 900, 'type' => 1,
        ]);
        $higherDamageButWrongValue->resources()->create([
            'Resources_values_idResources_values' => $resourceValue->idResources_values, 'number_value' => 3,
        ]);

        $params = http_build_query([
            'character_idcharacter' => $character->idcharacter,
            'resources' => json_encode([$resource->idgame_resources => 2]),
        ]);

        $response = $this->get(route('games.tabs.randomizer-top-combo', $game).'?'.$params);

        $response->assertOk();
        $response->assertSee('2A &gt; 5C', false);
        $response->assertDontSee('2A &gt; 236B', false);
    }
}
