<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GameLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_user_can_upload_a_game_logo(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('admin.game.update', $game), [
            'action' => 'Submit',
            'title' => 'Test Game',
            'image' => UploadedFile::fake()->create('logo.jpg', 10, 'image/jpeg'),
        ])->assertRedirect(route('admin.game.edit', $game));

        $game->refresh();
        $this->assertNotNull($game->image);
        Storage::disk('public')->assertExists($game->image);
        $this->assertSame(Storage::url($game->image), $game->logo_url);
    }

    public function test_updating_without_a_new_file_keeps_the_existing_logo(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'image' => 'game-logos/existing.jpg']);
        Storage::disk('public')->put('game-logos/existing.jpg', 'fake-content');

        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('admin.game.update', $game), [
            'action' => 'Submit',
            'title' => 'Test Game Renamed',
        ])->assertRedirect(route('admin.game.edit', $game));

        $game->refresh();
        $this->assertSame('Test Game Renamed', $game->name);
        $this->assertSame('game-logos/existing.jpg', $game->image);
        Storage::disk('public')->assertExists('game-logos/existing.jpg');
    }

    public function test_uploading_a_new_logo_replaces_and_deletes_the_old_file(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'image' => 'game-logos/old.jpg']);
        Storage::disk('public')->put('game-logos/old.jpg', 'fake-content');

        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('admin.game.update', $game), [
            'action' => 'Submit',
            'title' => 'Test Game',
            'image' => UploadedFile::fake()->create('new.jpg', 10, 'image/jpeg'),
        ]);

        Storage::disk('public')->assertMissing('game-logos/old.jpg');
        $this->assertNotSame('game-logos/old.jpg', $game->fresh()->image);
    }

    public function test_legacy_external_url_logo_is_preserved_and_not_deleted_as_a_local_file(): void
    {
        Storage::fake('public');

        $game = Game::create([
            'name' => 'Test Game',
            'complete' => 1,
            'modPass' => 'secret',
            'image' => 'https://example.com/legacy-logo.png',
        ]);

        $this->assertSame('https://example.com/legacy-logo.png', $game->logo_url);

        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('admin.game.update', $game), [
            'action' => 'Submit',
            'title' => 'Test Game Renamed',
        ]);

        $game->refresh();
        $this->assertSame('https://example.com/legacy-logo.png', $game->image);
        $this->assertSame('https://example.com/legacy-logo.png', $game->logo_url);
    }

    public function test_deleting_a_game_removes_its_stored_logo_file(): void
    {
        Storage::fake('public');

        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret', 'image' => 'game-logos/existing.jpg']);
        Storage::disk('public')->put('game-logos/existing.jpg', 'fake-content');

        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('admin.game.update', $game), ['action' => 'Delete']);

        Storage::disk('public')->assertMissing('game-logos/existing.jpg');
        $this->assertDatabaseMissing('game', ['idgame' => $game->idgame]);
    }
}
