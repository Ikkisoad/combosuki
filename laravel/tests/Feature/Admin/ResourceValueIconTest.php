<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\GameResource;
use App\Models\ResourceValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResourceValueIconTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsModerator(Game $game): User
    {
        $moderator = User::create(['nickname' => 'moderator', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($moderator->iduser);
        $this->actingAs($moderator);

        return $moderator;
    }

    private function createListResource(Game $game): GameResource
    {
        return GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Moon Type',
            'type' => 1,
            'primaryORsecundary' => 1,
        ]);
    }

    public function test_moderator_can_upload_an_icon_when_adding_a_resource_value(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = $this->createListResource($game);
        $this->actingAsModerator($game);

        $this->post(route('admin.resources.values.store', [$game, $resource]), [
            'action' => 'EditAdd',
            'resourcevalue' => 'Half Moon',
            'icon' => UploadedFile::fake()->create('half-moon.jpg', 10, 'image/jpeg'),
        ])->assertRedirect(route('admin.resources.values', [$game, $resource]));

        $value = ResourceValue::where('game_resources_idgame_resources', $resource->idgame_resources)->firstOrFail();
        $this->assertNotNull($value->icon);
        Storage::disk('public')->assertExists($value->icon);
    }

    public function test_updating_a_value_without_a_new_file_keeps_the_existing_icon(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = $this->createListResource($game);
        $value = ResourceValue::create([
            'value' => 'Half Moon',
            'icon' => 'resource-value-icons/existing.jpg',
            'game_resources_idgame_resources' => $resource->idgame_resources,
        ]);
        Storage::disk('public')->put('resource-value-icons/existing.jpg', 'fake-content');

        $this->actingAsModerator($game);

        $this->post(route('admin.resources.values.store', [$game, $resource]), [
            'action' => 'EditUpdate',
            'idresourcevalue' => $value->idResources_values,
            'resourcevalue' => 'Half Moon Renamed',
        ])->assertRedirect(route('admin.resources.values', [$game, $resource]));

        $value->refresh();
        $this->assertSame('Half Moon Renamed', $value->value);
        $this->assertSame('resource-value-icons/existing.jpg', $value->icon);
        Storage::disk('public')->assertExists('resource-value-icons/existing.jpg');
    }

    public function test_uploading_a_new_icon_replaces_and_deletes_the_old_file(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = $this->createListResource($game);
        $value = ResourceValue::create([
            'value' => 'Half Moon',
            'icon' => 'resource-value-icons/old.jpg',
            'game_resources_idgame_resources' => $resource->idgame_resources,
        ]);
        Storage::disk('public')->put('resource-value-icons/old.jpg', 'fake-content');

        $this->actingAsModerator($game);

        $this->post(route('admin.resources.values.store', [$game, $resource]), [
            'action' => 'EditUpdate',
            'idresourcevalue' => $value->idResources_values,
            'resourcevalue' => 'Half Moon',
            'icon' => UploadedFile::fake()->create('new.jpg', 10, 'image/jpeg'),
        ]);

        Storage::disk('public')->assertMissing('resource-value-icons/old.jpg');
        $newIcon = $value->fresh()->icon;
        $this->assertNotSame('resource-value-icons/old.jpg', $newIcon);
        Storage::disk('public')->assertExists($newIcon);
    }

    public function test_deleting_a_value_removes_its_stored_icon_file(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = $this->createListResource($game);
        $value = ResourceValue::create([
            'value' => 'Half Moon',
            'icon' => 'resource-value-icons/existing.jpg',
            'game_resources_idgame_resources' => $resource->idgame_resources,
        ]);
        Storage::disk('public')->put('resource-value-icons/existing.jpg', 'fake-content');

        $this->actingAsModerator($game);

        $this->post(route('admin.resources.values.store', [$game, $resource]), [
            'action' => 'EditDelete',
            'idresourcevalue' => $value->idResources_values,
        ]);

        Storage::disk('public')->assertMissing('resource-value-icons/existing.jpg');
        $this->assertDatabaseMissing('resources_values', ['idResources_values' => $value->idResources_values]);
    }

    public function test_deleting_a_resource_removes_stored_icon_files_for_all_its_values(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $resource = $this->createListResource($game);
        $value = ResourceValue::create([
            'value' => 'Half Moon',
            'icon' => 'resource-value-icons/existing.jpg',
            'game_resources_idgame_resources' => $resource->idgame_resources,
        ]);
        Storage::disk('public')->put('resource-value-icons/existing.jpg', 'fake-content');

        $this->actingAsModerator($game);

        $this->post(route('admin.resources.store', $game), [
            'action' => 'Delete',
            'idresource' => $resource->idgame_resources,
        ]);

        Storage::disk('public')->assertMissing('resource-value-icons/existing.jpg');
        $this->assertDatabaseMissing('resources_values', ['idResources_values' => $value->idResources_values]);
    }
}
