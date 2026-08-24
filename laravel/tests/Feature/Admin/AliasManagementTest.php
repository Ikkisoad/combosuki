<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AliasManagementTest extends TestCase
{
    use RefreshDatabase;

    private function trustedUser(Game $game): User
    {
        $user = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($user->iduser);

        return $user;
    }

    public function test_game_edit_page_shows_current_aliases(): void
    {
        // Multi-word name -> auto-generated "TF" alias (see Game::booted()).
        $game = Game::create(['name' => 'Test Fighter', 'complete' => 1, 'modPass' => 'secret']);

        $this->actingAs($this->trustedUser($game));

        $this->get(route('admin.game.edit', $game))->assertOk()->assertSee('TF', false);
    }

    public function test_updating_a_game_syncs_its_aliases(): void
    {
        $game = Game::create(['name' => 'Test Fighter', 'complete' => 1, 'modPass' => 'secret']);
        $game->aliases()->create(['alias' => 'OLD']);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.game.update', $game), [
            'action' => 'Submit',
            'title' => 'Test Fighter',
            'aliases' => 'TF, Test Fighter 2',
        ])->assertRedirect(route('admin.game.edit', $game));

        $this->assertSame(['TF', 'Test Fighter 2'], $game->aliases()->orderBy('idgamealias')->pluck('alias')->all());
    }

    public function test_a_game_cannot_take_another_games_alias(): void
    {
        $other = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $other->aliases()->create(['alias' => 'TAKEN']);

        // Multi-word name -> "TF" auto-alias (see Game::booted()), which the
        // rejected submission below must leave untouched.
        $game = Game::create(['name' => 'Test Fighter', 'complete' => 1, 'modPass' => 'secret']);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.game.update', $game), [
            'action' => 'Submit',
            'title' => 'Test Fighter',
            'aliases' => 'TAKEN',
        ])->assertSessionHasErrors();

        $this->assertSame(['TF'], $game->aliases()->pluck('alias')->all());
    }

    public function test_adding_a_character_with_an_alias_syncs_it(): void
    {
        $game = Game::create(['name' => 'Test Fighter', 'complete' => 1, 'modPass' => 'secret']);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.characters.store', $game), [
            'action' => 'Add',
            'character' => 'Val Longcoat',
            'aliases' => 'Val, V',
        ])->assertRedirect(route('admin.characters.index', $game));

        $character = Character::where('game_idgame', $game->idgame)->firstOrFail();
        $this->assertSame(['Val', 'V'], $character->aliases()->orderBy('idcharacteralias')->pluck('alias')->all());
    }

    public function test_two_characters_in_the_same_game_cannot_share_an_alias(): void
    {
        $game = Game::create(['name' => 'Test Fighter', 'complete' => 1, 'modPass' => 'secret']);
        $existing = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $existing->aliases()->create(['alias' => 'V', 'game_idgame' => $game->idgame]);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.characters.store', $game), [
            'action' => 'Add',
            'character' => 'Vlad',
            'aliases' => 'V',
        ])->assertSessionHasErrors();

        $this->assertDatabaseMissing('character', ['name' => 'Vlad']);
    }

    public function test_characters_in_different_games_can_share_an_alias(): void
    {
        $gameA = Game::create(['name' => 'Game A', 'complete' => 1, 'modPass' => 'secret']);
        $gameB = Game::create(['name' => 'Game B', 'complete' => 1, 'modPass' => 'secret']);
        $characterA = Character::create(['name' => 'Valentine', 'game_idgame' => $gameA->idgame]);
        $characterA->aliases()->create(['alias' => 'V', 'game_idgame' => $gameA->idgame]);

        $this->actingAs($this->trustedUser($gameB));

        $this->post(route('admin.characters.store', $gameB), [
            'action' => 'Add',
            'character' => 'Vega',
            'aliases' => 'V',
        ])->assertRedirect(route('admin.characters.index', $gameB));

        $this->assertDatabaseHas('character', ['name' => 'Vega', 'game_idgame' => $gameB->idgame]);
    }
}
