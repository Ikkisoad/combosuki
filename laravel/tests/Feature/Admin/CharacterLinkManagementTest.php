<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterLinkManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_character_saves_its_links(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.characters.store', $game), [
            'action' => 'Add',
            'character' => 'Valentine',
            'links' => "Wiki|https://example.com/wiki\nFrame Data|https://example.com/frames",
        ])->assertRedirect(route('admin.characters.index', $game));

        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $links = $character->links()->orderBy('order')->get();

        $this->assertCount(2, $links);
        $this->assertSame('Wiki', $links[0]->label);
        $this->assertSame('https://example.com/wiki', $links[0]->url);
        $this->assertSame('Frame Data', $links[1]->label);
        $this->assertSame('https://example.com/frames', $links[1]->url);
    }

    public function test_updating_a_character_replaces_its_links(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $character->links()->create(['label' => 'Old Link', 'url' => 'https://old.example.com', 'order' => 0]);

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.characters.store', $game), [
            'action' => 'Update',
            'idcharacter' => $character->idcharacter,
            'character' => 'Valentine',
            'links' => 'New Link|https://new.example.com',
        ])->assertRedirect(route('admin.characters.index', $game));

        $links = $character->links()->get();
        $this->assertCount(1, $links);
        $this->assertSame('New Link', $links[0]->label);
        $this->assertSame('https://new.example.com', $links[0]->url);
    }

    public function test_lines_without_a_label_and_url_pair_are_ignored(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.characters.store', $game), [
            'action' => 'Add',
            'character' => 'Valentine',
            'links' => "not a link\nWiki|https://example.com/wiki\n|https://missing-label.com\nNo URL|",
        ]);

        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $links = $character->links()->get();

        $this->assertCount(1, $links);
        $this->assertSame('Wiki', $links[0]->label);
    }

    public function test_deleting_a_character_removes_its_links(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $character->links()->create(['label' => 'Wiki', 'url' => 'https://example.com/wiki', 'order' => 0]);

        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);
        $this->actingAs($trusted);

        $this->post(route('admin.characters.store', $game), [
            'action' => 'Delete',
            'idcharacter' => $character->idcharacter,
        ]);

        $this->assertDatabaseMissing('character_link', ['character_idcharacter' => $character->idcharacter]);
    }

    public function test_character_page_shows_configured_links(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $character->links()->create(['label' => 'Official Frame Data', 'url' => 'https://example.com/frames', 'order' => 0]);

        $response = $this->get(route('characters.show', [$game, $character]));

        $response->assertOk();
        $response->assertSee('Official Frame Data');
        $response->assertSee('https://example.com/frames', false);
    }
}
