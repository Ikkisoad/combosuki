<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CharacterBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_edit_form_renders_inputs_for_every_character_bound_to_the_shared_form(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $characterA = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $characterB = Character::create(['name' => 'Painwheel', 'game_idgame' => $game->idgame]);

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $response = $this->get(route('admin.characters.index', $game));

        $response->assertOk();
        $response->assertSee('id="bulk-characters-form"', false);
        $response->assertSee("characters[{$characterA->idcharacter}][name]", false);
        $response->assertSee("characters[{$characterB->idcharacter}][name]", false);
        $response->assertSee('Save All');
    }

    public function test_bulk_update_saves_every_submitted_character_in_one_request(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $characterA = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $characterB = Character::create(['name' => 'Painwheel', 'game_idgame' => $game->idgame]);

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $response = $this->post(route('admin.characters.bulkUpdate', $game), [
            'characters' => [
                $characterA->idcharacter => ['name' => 'Valentine Renamed'],
                $characterB->idcharacter => ['name' => 'Painwheel Renamed'],
            ],
        ]);

        $response->assertRedirect(route('admin.characters.index', $game));

        $this->assertSame('Valentine Renamed', $characterA->fresh()->name);
        $this->assertSame('Painwheel Renamed', $characterB->fresh()->name);
    }

    public function test_bulk_update_can_replace_a_character_portrait(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame, 'image' => 'character-portraits/old.jpg']);
        Storage::disk('public')->put('character-portraits/old.jpg', 'fake-content');

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.characters.bulkUpdate', $game), [
            'characters' => [
                $character->idcharacter => [
                    'name' => 'Valentine',
                    'image' => UploadedFile::fake()->create('new.jpg', 10, 'image/jpeg'),
                ],
            ],
        ]);

        Storage::disk('public')->assertMissing('character-portraits/old.jpg');
        $this->assertNotSame('character-portraits/old.jpg', $character->fresh()->image);
        Storage::disk('public')->assertExists($character->fresh()->image);
    }

    public function test_bulk_update_without_a_new_file_keeps_the_existing_portrait(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame, 'image' => 'character-portraits/existing.jpg']);
        Storage::disk('public')->put('character-portraits/existing.jpg', 'fake-content');

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.characters.bulkUpdate', $game), [
            'characters' => [
                $character->idcharacter => ['name' => 'Valentine Renamed'],
            ],
        ]);

        $character->refresh();
        $this->assertSame('Valentine Renamed', $character->name);
        $this->assertSame('character-portraits/existing.jpg', $character->image);
        Storage::disk('public')->assertExists('character-portraits/existing.jpg');
    }

    public function test_bulk_update_cannot_touch_characters_belonging_to_another_game(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $foreignCharacter = Character::create(['name' => 'Filia', 'game_idgame' => $otherGame->idgame]);

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.characters.bulkUpdate', $game), [
            'characters' => [
                $foreignCharacter->idcharacter => ['name' => 'HACKED'],
            ],
        ]);

        $this->assertSame('Filia', $foreignCharacter->fresh()->name);
    }
}
