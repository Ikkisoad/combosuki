<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameResource;
use App\Models\ResourceValue;
use App\Models\TierList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierListResourceModeTest extends TestCase
{
    use RefreshDatabase;

    private function makeGameWithResource(): array
    {
        $game = Game::create(['name' => 'Melty Blood', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Sion', 'game_idgame' => $game->idgame]);

        $resource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Moon Type',
            'type' => 1,
            'primaryORsecundary' => 1,
            'include_in_tier_lists' => true,
        ]);

        $crescent = ResourceValue::create(['value' => 'Crescent', 'order' => 1, 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $full = ResourceValue::create(['value' => 'Full', 'order' => 2, 'game_resources_idgame_resources' => $resource->idgame_resources]);

        return [$game, $character, $resource, $crescent, $full];
    }

    private function actingAsRegularUser(): void
    {
        $this->actingAs(User::create(['nickname' => 'regular', 'password' => 'password123']));
    }

    public function test_entry_requires_a_resource_value_when_the_game_has_one_configured(): void
    {
        [$game, $character] = $this->makeGameWithResource();
        $this->actingAsRegularUser();

        $response = $this->post(route('tier-lists.store'), [
            'title' => 'No Value List',
            'game_idgame' => $game->idgame,
            'entries' => [
                ['character_idcharacter' => $character->idcharacter, 'tier' => 'S'],
            ],
        ]);

        $response->assertSessionHasErrors('entries.0.resources_values_idResources_values');
        $this->assertSame(0, TierList::count());
    }

    public function test_entry_rejects_a_resource_value_from_a_different_resource(): void
    {
        [$game, $character] = $this->makeGameWithResource();

        $otherResource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Other',
            'type' => 1,
            'primaryORsecundary' => 0,
        ]);
        $otherValue = ResourceValue::create(['value' => 'X', 'game_resources_idgame_resources' => $otherResource->idgame_resources]);

        $this->actingAsRegularUser();

        $response = $this->post(route('tier-lists.store'), [
            'title' => 'Wrong Value List',
            'game_idgame' => $game->idgame,
            'entries' => [
                [
                    'character_idcharacter' => $character->idcharacter,
                    'resources_values_idResources_values' => $otherValue->idResources_values,
                    'tier' => 'S',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('entries.0.resources_values_idResources_values');
    }

    public function test_same_character_can_appear_in_multiple_tiers_under_different_resource_values(): void
    {
        [$game, $character, , $crescent, $full] = $this->makeGameWithResource();
        $this->actingAsRegularUser();

        $response = $this->post(route('tier-lists.store'), [
            'title' => 'Moon Type List',
            'game_idgame' => $game->idgame,
            'entries' => [
                ['character_idcharacter' => $character->idcharacter, 'resources_values_idResources_values' => $crescent->idResources_values, 'tier' => 'S'],
                ['character_idcharacter' => $character->idcharacter, 'resources_values_idResources_values' => $full->idResources_values, 'tier' => 'F'],
            ],
        ]);

        $tierList = TierList::sole();
        $response->assertRedirect(route('tier-lists.show', $tierList));

        $this->assertSame(2, $tierList->entries()->count());
        $this->assertSame('S', $tierList->entries()->where('resources_values_idResources_values', $crescent->idResources_values)->first()->tier);
        $this->assertSame('F', $tierList->entries()->where('resources_values_idResources_values', $full->idResources_values)->first()->tier);
    }

    public function test_duplicate_character_and_resource_value_pair_is_rejected(): void
    {
        [$game, $character, , $crescent] = $this->makeGameWithResource();
        $this->actingAsRegularUser();

        $response = $this->post(route('tier-lists.store'), [
            'title' => 'Duplicate List',
            'game_idgame' => $game->idgame,
            'entries' => [
                ['character_idcharacter' => $character->idcharacter, 'resources_values_idResources_values' => $crescent->idResources_values, 'tier' => 'S'],
                ['character_idcharacter' => $character->idcharacter, 'resources_values_idResources_values' => $crescent->idResources_values, 'tier' => 'A'],
            ],
        ]);

        $response->assertSessionHasErrors('entries');
        $this->assertSame(0, TierList::count());
    }
}
