<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameResource;
use App\Models\ResourceValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboSecondaryResourceFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_advanced_search_page_shows_secondary_resource_fields(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $assist = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Assist',
            'type' => 1,
            'primaryORsecundary' => 0,
        ]);
        ResourceValue::create(['value' => 'Rockman', 'game_resources_idgame_resources' => $assist->idgame_resources]);

        $response = $this->get(route('games.combos.index', $game));

        $response->assertOk()
            ->assertSee('Secondary Resources')
            ->assertSee('name="Assist"', false)
            ->assertSee('Rockman');
    }

    public function test_searching_by_secondary_resource_value_filters_results(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);

        $assist = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Assist',
            'type' => 1,
            'primaryORsecundary' => 0,
        ]);
        $rockman = ResourceValue::create(['value' => 'Rockman', 'game_resources_idgame_resources' => $assist->idgame_resources]);
        $tron = ResourceValue::create(['value' => 'Tron', 'game_resources_idgame_resources' => $assist->idgame_resources]);

        $matching = Combo::create([
            'combo' => '5A > 5B',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 0,
            'type' => 1,
        ]);
        $matching->resources()->create(['Resources_values_idResources_values' => $rockman->idResources_values]);

        $other = Combo::create([
            'combo' => '2C',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 0,
            'type' => 1,
        ]);
        $other->resources()->create(['Resources_values_idResources_values' => $tron->idResources_values]);

        $response = $this->get(route('games.combos.index', $game).'?Assist='.$rockman->idResources_values);

        $response->assertOk();
        $response->assertViewHas('combos', function ($combos) use ($matching, $other) {
            $ids = $combos->pluck('idcombo');

            return $ids->contains($matching->idcombo) && ! $ids->contains($other->idcombo);
        });
    }
}
