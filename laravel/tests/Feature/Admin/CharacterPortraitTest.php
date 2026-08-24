<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CharacterPortraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_user_can_upload_a_character_portrait(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.characters.store', $game), [
            'action' => 'Add',
            'character' => 'Valentine',
            'image' => UploadedFile::fake()->create('valentine.jpg', 10, 'image/jpeg'),
        ])->assertRedirect(route('admin.characters.index', $game));

        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $this->assertNotNull($character->image);
        Storage::disk('public')->assertExists($character->image);
    }

    public function test_updating_name_without_a_new_file_keeps_existing_portrait(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame, 'image' => 'character-portraits/existing.jpg']);
        Storage::disk('public')->put('character-portraits/existing.jpg', 'fake-content');

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.characters.store', $game), [
            'action' => 'Update',
            'idcharacter' => $character->idcharacter,
            'character' => 'Valentine Renamed',
        ])->assertRedirect(route('admin.characters.index', $game));

        $character->refresh();
        $this->assertSame('Valentine Renamed', $character->name);
        $this->assertSame('character-portraits/existing.jpg', $character->image);
        Storage::disk('public')->assertExists('character-portraits/existing.jpg');
    }

    public function test_uploading_a_new_portrait_replaces_and_deletes_the_old_file(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame, 'image' => 'character-portraits/old.jpg']);
        Storage::disk('public')->put('character-portraits/old.jpg', 'fake-content');

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.characters.store', $game), [
            'action' => 'Update',
            'idcharacter' => $character->idcharacter,
            'character' => 'Valentine',
            'image' => UploadedFile::fake()->create('new.jpg', 10, 'image/jpeg'),
        ]);

        Storage::disk('public')->assertMissing('character-portraits/old.jpg');
        $this->assertNotSame('character-portraits/old.jpg', $character->fresh()->image);
    }

    public function test_deleting_character_removes_stored_portrait_file(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame, 'image' => 'character-portraits/existing.jpg']);
        Storage::disk('public')->put('character-portraits/existing.jpg', 'fake-content');

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.characters.store', $game), [
            'action' => 'Delete',
            'idcharacter' => $character->idcharacter,
        ]);

        Storage::disk('public')->assertMissing('character-portraits/existing.jpg');
        $this->assertDatabaseMissing('character', ['idcharacter' => $character->idcharacter]);
    }
}
