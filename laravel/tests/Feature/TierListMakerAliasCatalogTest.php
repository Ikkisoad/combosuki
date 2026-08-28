<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterResourceValueAlias;
use App\Models\Game;
use App\Models\GameResource;
use App\Models\ResourceValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TierListMakerAliasCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_maker_catalog_resolves_each_characters_own_alias_and_icon(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Melty Blood', 'complete' => 1, 'modPass' => 'secret']);
        $comboChan = Character::create(['name' => 'Combo-chan', 'game_idgame' => $game->idgame]);
        $okizemeKun = Character::create(['name' => 'Okizeme-kun', 'game_idgame' => $game->idgame]);

        $resource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Support',
            'type' => 1,
            'primaryORsecundary' => 1,
            'include_in_tier_lists' => true,
        ]);

        $one = ResourceValue::create(['value' => '1', 'order' => 1, 'icon' => 'resource-value-icons/default.png', 'game_resources_idgame_resources' => $resource->idgame_resources]);
        Storage::disk('public')->put('resource-value-icons/default.png', 'fake-content');
        Storage::disk('public')->put('resource-value-icons/a.png', 'fake-content');

        CharacterResourceValueAlias::create([
            'alias' => 'A',
            'icon' => 'resource-value-icons/a.png',
            'character_idcharacter' => $comboChan->idcharacter,
            'resources_values_idResources_values' => $one->idResources_values,
        ]);

        $this->actingAs(User::create(['nickname' => 'regular', 'password' => 'password123']));

        $response = $this->get(route('tier-lists.create'));

        $response->assertOk();

        preg_match('/id="tier-list-catalog">(.*?)<\/script>/s', $response->getContent(), $matches);
        $catalog = json_decode($matches[1], true)[$game->idgame];

        $comboChanEntry = collect($catalog['characters'])->firstWhere('idcharacter', $comboChan->idcharacter);
        $okizemeKunEntry = collect($catalog['characters'])->firstWhere('idcharacter', $okizemeKun->idcharacter);

        $this->assertSame('A', $comboChanEntry['resourceValues'][0]['value']);
        $this->assertStringContainsString('resource-value-icons/a.png', $comboChanEntry['resourceValues'][0]['icon']);

        // Okizeme-kun has no alias for this value, so the maker must fall
        // back to the resource value's own default text/icon.
        $this->assertSame('1', $okizemeKunEntry['resourceValues'][0]['value']);
        $this->assertStringContainsString('resource-value-icons/default.png', $okizemeKunEntry['resourceValues'][0]['icon']);
    }
}
