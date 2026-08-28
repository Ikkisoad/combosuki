<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\CharacterResourceValueAlias;
use App\Models\Game;
use App\Models\GameResource;
use App\Models\ResourceValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResourceValueAliasManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsModerator(Game $game): User
    {
        $moderator = User::create(['nickname' => 'moderator', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($moderator->iduser);
        $this->actingAs($moderator);

        return $moderator;
    }

    private function createSupportResource(Game $game): GameResource
    {
        return GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Support',
            'type' => 1,
            'primaryORsecundary' => 1,
        ]);
    }

    private function createCharacter(Game $game, string $name = 'Combo-chan'): Character
    {
        return Character::create(['name' => $name, 'game_idgame' => $game->idgame]);
    }

    public function test_moderator_can_add_a_character_alias_with_an_icon(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = $this->createSupportResource($game);
        $value = ResourceValue::create(['value' => '1', 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $character = $this->createCharacter($game);

        $this->actingAsModerator($game);

        $this->post(route('admin.resources.aliases.store', [$game, $resource]), [
            'aliases' => [
                $character->idcharacter => [
                    $value->idResources_values => [
                        'alias' => 'A',
                        'icon' => UploadedFile::fake()->create('a.jpg', 10, 'image/jpeg'),
                    ],
                ],
            ],
        ])->assertRedirect(route('admin.resources.aliases', [$game, $resource]));

        $alias = CharacterResourceValueAlias::where('character_idcharacter', $character->idcharacter)
            ->where('resources_values_idResources_values', $value->idResources_values)
            ->firstOrFail();

        $this->assertSame('A', $alias->alias);
        $this->assertNotNull($alias->icon);
        Storage::disk('public')->assertExists($alias->icon);
    }

    public function test_leaving_the_alias_text_blank_deletes_the_row_and_its_icon(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = $this->createSupportResource($game);
        $value = ResourceValue::create(['value' => '1', 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $character = $this->createCharacter($game);

        Storage::disk('public')->put('resource-value-icons/existing.jpg', 'fake-content');
        $alias = CharacterResourceValueAlias::create([
            'alias' => 'A',
            'icon' => 'resource-value-icons/existing.jpg',
            'character_idcharacter' => $character->idcharacter,
            'resources_values_idResources_values' => $value->idResources_values,
        ]);

        $this->actingAsModerator($game);

        $this->post(route('admin.resources.aliases.store', [$game, $resource]), [
            'aliases' => [
                $character->idcharacter => [
                    $value->idResources_values => ['alias' => ''],
                ],
            ],
        ])->assertRedirect(route('admin.resources.aliases', [$game, $resource]));

        Storage::disk('public')->assertMissing('resource-value-icons/existing.jpg');
        $this->assertDatabaseMissing('character_resource_value_alias', ['idcharacterresourcevaluealias' => $alias->idcharacterresourcevaluealias]);
    }

    public function test_uploading_a_new_icon_replaces_and_deletes_the_old_file(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = $this->createSupportResource($game);
        $value = ResourceValue::create(['value' => '1', 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $character = $this->createCharacter($game);

        Storage::disk('public')->put('resource-value-icons/old.jpg', 'fake-content');
        $alias = CharacterResourceValueAlias::create([
            'alias' => 'A',
            'icon' => 'resource-value-icons/old.jpg',
            'character_idcharacter' => $character->idcharacter,
            'resources_values_idResources_values' => $value->idResources_values,
        ]);

        $this->actingAsModerator($game);

        $this->post(route('admin.resources.aliases.store', [$game, $resource]), [
            'aliases' => [
                $character->idcharacter => [
                    $value->idResources_values => [
                        'alias' => 'A',
                        'icon' => UploadedFile::fake()->create('new.jpg', 10, 'image/jpeg'),
                    ],
                ],
            ],
        ]);

        Storage::disk('public')->assertMissing('resource-value-icons/old.jpg');
        $newIcon = $alias->fresh()->icon;
        $this->assertNotSame('resource-value-icons/old.jpg', $newIcon);
        Storage::disk('public')->assertExists($newIcon);
    }

    public function test_deleting_the_character_cascades_to_its_aliases(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = $this->createSupportResource($game);
        $value = ResourceValue::create(['value' => '1', 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $character = $this->createCharacter($game);
        $alias = CharacterResourceValueAlias::create([
            'alias' => 'A',
            'character_idcharacter' => $character->idcharacter,
            'resources_values_idResources_values' => $value->idResources_values,
        ]);

        $character->delete();

        $this->assertDatabaseMissing('character_resource_value_alias', ['idcharacterresourcevaluealias' => $alias->idcharacterresourcevaluealias]);
    }

    public function test_deleting_the_resource_value_cascades_to_its_aliases(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = $this->createSupportResource($game);
        $value = ResourceValue::create(['value' => '1', 'game_resources_idgame_resources' => $resource->idgame_resources]);
        $character = $this->createCharacter($game);
        $alias = CharacterResourceValueAlias::create([
            'alias' => 'A',
            'character_idcharacter' => $character->idcharacter,
            'resources_values_idResources_values' => $value->idResources_values,
        ]);

        $value->delete();

        $this->assertDatabaseMissing('character_resource_value_alias', ['idcharacterresourcevaluealias' => $alias->idcharacterresourcevaluealias]);
    }

    public function test_a_non_moderator_cannot_manage_resource_value_aliases(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = $this->createSupportResource($game);
        $user = User::create(['nickname' => 'rando', 'password' => 'password123']);

        $this->actingAs($user);

        $this->get(route('admin.resources.aliases', [$game, $resource]))->assertRedirect()->assertSessionHas('error');
    }

    public function test_aliases_page_404s_for_a_non_list_resource(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Meter',
            'type' => 2,
            'primaryORsecundary' => 1,
        ]);
        ResourceValue::create(['value' => '100', 'game_resources_idgame_resources' => $resource->idgame_resources]);

        $this->actingAsModerator($game);

        $this->get(route('admin.resources.aliases', [$game, $resource]))->assertNotFound();
    }
}
