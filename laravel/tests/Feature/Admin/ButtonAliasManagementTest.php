<?php

namespace Tests\Feature\Admin;

use App\Models\Button;
use App\Models\ButtonAlias;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ButtonAliasManagementTest extends TestCase
{
    use RefreshDatabase;

    private function trustedUser(Game $game): User
    {
        $user = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($user->iduser);

        return $user;
    }

    private function throwButton(Game $game): Button
    {
        return Button::create(['name' => 'LP+LK', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame]);
    }

    public function test_adding_a_button_alias(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->throwButton($game);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.button-aliases.store', $game), [
            'action' => 'Add',
            'alias' => 'Throw',
            'button_idbutton' => $button->idbutton,
        ])->assertRedirect(route('admin.button-aliases.index', $game));

        $this->assertDatabaseHas('button_alias', [
            'alias' => 'Throw',
            'button_idbutton' => $button->idbutton,
            'game_idgame' => $game->idgame,
        ]);
    }

    public function test_a_button_alias_cannot_point_at_a_button_from_another_game(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $foreignButton = $this->throwButton($otherGame);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.button-aliases.store', $game), [
            'action' => 'Add',
            'alias' => 'Throw',
            'button_idbutton' => $foreignButton->idbutton,
        ])->assertSessionHasErrors();

        $this->assertSame(0, ButtonAlias::where('game_idgame', $game->idgame)->count());
    }

    public function test_updating_a_button_alias(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->throwButton($game);
        $otherButton = Button::create(['name' => 'A+B', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame]);
        $alias = $game->buttonAliases()->create(['alias' => 'Throw', 'button_idbutton' => $button->idbutton]);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.button-aliases.store', $game), [
            'action' => 'Update',
            'idbuttonalias' => $alias->idbuttonalias,
            'alias' => 'Throw',
            'button_idbutton' => $otherButton->idbutton,
        ])->assertRedirect(route('admin.button-aliases.index', $game));

        $this->assertSame($otherButton->idbutton, $alias->fresh()->button_idbutton);
    }

    public function test_deleting_a_button_alias(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->throwButton($game);
        $alias = $game->buttonAliases()->create(['alias' => 'Throw', 'button_idbutton' => $button->idbutton]);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.button-aliases.store', $game), [
            'action' => 'Delete',
            'idbuttonalias' => $alias->idbuttonalias,
        ])->assertRedirect(route('admin.button-aliases.index', $game));

        $this->assertDatabaseMissing('button_alias', ['idbuttonalias' => $alias->idbuttonalias]);
    }

    public function test_two_aliases_in_the_same_game_cannot_share_an_alias_word(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->throwButton($game);
        $game->buttonAliases()->create(['alias' => 'Throw', 'button_idbutton' => $button->idbutton]);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.button-aliases.store', $game), [
            'action' => 'Add',
            'alias' => 'throw',
            'button_idbutton' => $button->idbutton,
        ])->assertSessionHasErrors();

        $this->assertSame(1, ButtonAlias::where('game_idgame', $game->idgame)->count());
    }

    public function test_aliases_in_different_games_can_share_an_alias_word(): void
    {
        $gameA = Game::create(['name' => 'Game A', 'complete' => 1, 'modPass' => 'secret']);
        $gameB = Game::create(['name' => 'Game B', 'complete' => 1, 'modPass' => 'secret']);
        $buttonA = $this->throwButton($gameA);
        $buttonB = $this->throwButton($gameB);
        $gameA->buttonAliases()->create(['alias' => 'Throw', 'button_idbutton' => $buttonA->idbutton]);

        $this->actingAs($this->trustedUser($gameB));

        $this->post(route('admin.button-aliases.store', $gameB), [
            'action' => 'Add',
            'alias' => 'Throw',
            'button_idbutton' => $buttonB->idbutton,
        ])->assertRedirect(route('admin.button-aliases.index', $gameB));

        $this->assertDatabaseHas('button_alias', ['alias' => 'Throw', 'game_idgame' => $gameB->idgame]);
    }

    public function test_bulk_update_saves_every_alias(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $button = $this->throwButton($game);
        $alias = $game->buttonAliases()->create(['alias' => 'Throw', 'button_idbutton' => $button->idbutton]);

        $this->actingAs($this->trustedUser($game));

        $this->post(route('admin.button-aliases.bulkUpdate', $game), [
            'aliases' => [
                $alias->idbuttonalias => ['alias' => 'Grab', 'button_idbutton' => $button->idbutton],
            ],
        ])->assertRedirect(route('admin.button-aliases.index', $game));

        $this->assertSame('Grab', $alias->fresh()->alias);
    }

    public function test_a_non_moderator_cannot_manage_button_aliases(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $user = User::create(['nickname' => 'rando', 'password' => 'password123']);

        $this->actingAs($user);

        $this->get(route('admin.button-aliases.index', $game))->assertRedirect()->assertSessionHas('error');
    }
}
