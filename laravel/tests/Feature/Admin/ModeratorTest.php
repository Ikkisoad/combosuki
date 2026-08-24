<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeratorTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        return User::create(['nickname' => 'moderator', 'password' => 'password123', 'is_moderator' => true]);
    }

    private function admin(): User
    {
        return User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
    }

    private function game(): Game
    {
        return Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
    }

    public function test_moderator_cannot_edit_a_game_they_are_not_assigned_to(): void
    {
        $game = $this->game();

        $this->actingAs($this->moderator());

        $this->get(route('admin.game.edit', $game))->assertRedirect()->assertSessionHas('error');
        $this->post(route('admin.game.update', $game), ['action' => 'Submit', 'title' => 'Hacked'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame('Test Game', $game->fresh()->name);
    }

    /**
     * The "Edit Game" button on the public game page must track actual
     * edit access, not just being trusted-level, since a trusted user or
     * moderator without a game-specific assignment would otherwise get a
     * link straight into a 403.
     */
    public function test_edit_game_button_only_shows_for_users_who_can_actually_edit_the_game(): void
    {
        $game = $this->game();
        $moderator = $this->moderator();
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);

        $this->actingAs($moderator);
        $this->get(route('games.show', $game))->assertOk()->assertDontSee('Edit Game');

        $this->actingAs($trusted);
        $this->get(route('games.show', $game))->assertOk()->assertDontSee('Edit Game');

        $game->moderators()->attach($moderator->iduser);
        $this->actingAs($moderator);
        $this->get(route('games.show', $game))->assertOk()->assertSee('Edit Game');

        $this->actingAs($this->admin());
        $this->get(route('games.show', $game))->assertOk()->assertSee('Edit Game');
    }

    public function test_moderator_can_edit_a_game_they_are_assigned_to(): void
    {
        $game = $this->game();
        $moderator = $this->moderator();
        $game->moderators()->attach($moderator->iduser);

        $this->actingAs($moderator);

        $this->get(route('admin.game.edit', $game))->assertOk();
        $this->post(route('admin.game.update', $game), ['action' => 'Submit', 'title' => 'Renamed'])
            ->assertRedirect(route('admin.game.edit', $game));

        $this->assertSame('Renamed', $game->fresh()->name);
    }

    public function test_moderator_cannot_delete_a_game_they_are_assigned_to(): void
    {
        $game = $this->game();
        $moderator = $this->moderator();
        $game->moderators()->attach($moderator->iduser);

        $this->actingAs($moderator);

        $this->post(route('admin.game.update', $game), ['action' => 'Delete'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('game', ['idgame' => $game->idgame]);
    }

    public function test_moderator_creating_a_game_is_auto_assigned_as_its_moderator(): void
    {
        $moderator = $this->moderator();
        $this->actingAs($moderator);

        $this->post(route('games.store'), ['name' => 'New Game', 'image' => 'https://example.com/logo.png']);

        $game = Game::where('name', 'New Game')->firstOrFail();
        $this->assertTrue($game->moderators()->where('user.iduser', $moderator->iduser)->exists());

        $this->get(route('admin.game.edit', $game))->assertOk();
        $this->post(route('admin.game.update', $game), ['action' => 'Delete'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('game', ['idgame' => $game->idgame]);
    }

    public function test_trusted_user_creating_a_game_is_auto_assigned_as_its_moderator(): void
    {
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $this->actingAs($trusted);

        $this->post(route('games.store'), ['name' => 'New Game', 'image' => 'https://example.com/logo.png']);

        $game = Game::where('name', 'New Game')->firstOrFail();

        $this->get(route('admin.game.edit', $game))->assertOk();
        $this->post(route('admin.game.update', $game), ['action' => 'Delete'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('game', ['idgame' => $game->idgame]);
    }

    public function test_only_admin_can_delete_a_game_even_its_creator(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->post(route('games.store'), ['name' => 'New Game', 'image' => 'https://example.com/logo.png']);
        $game = Game::where('name', 'New Game')->firstOrFail();

        $this->post(route('admin.game.update', $game), ['action' => 'Delete'])
            ->assertRedirect(route('games.index'));

        $this->assertDatabaseMissing('game', ['idgame' => $game->idgame]);
    }

    public function test_moderator_can_view_user_list_and_toggle_trusted_flag(): void
    {
        $moderator = $this->moderator();
        $user = User::create(['nickname' => 'regular', 'password' => 'password123']);

        $this->actingAs($moderator);

        $this->get(route('admin.users.index'))->assertOk();

        $this->post(route('admin.users.trusted.update', $user))->assertRedirect(route('admin.users.index'));
        $this->assertTrue($user->fresh()->trusted_user);
    }

    public function test_moderator_cannot_reach_admin_only_user_management_actions(): void
    {
        $moderator = $this->moderator();
        $user = User::create(['nickname' => 'regular', 'password' => 'password123']);

        $this->actingAs($moderator);

        $this->post(route('admin.users.store'), [
            'nickname' => 'hacker', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect()->assertSessionHas('error');

        $this->post(route('admin.users.moderator.update', $user))->assertRedirect()->assertSessionHas('error');
        $this->get(route('admin.users.moderated-games.edit', $user))->assertRedirect()->assertSessionHas('error');
        $this->post(route('admin.users.moderated-games.update', $user), ['game_ids' => []])->assertRedirect()->assertSessionHas('error');
        $this->post(route('admin.users.password.update', $user), [
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertFalse($user->fresh()->is_moderator);
    }

    public function test_admin_can_assign_and_unassign_games_for_a_moderator(): void
    {
        $admin = $this->admin();
        $moderator = $this->moderator();
        $gameA = Game::create(['name' => 'Game A', 'complete' => 1, 'modPass' => 'secret']);
        $gameB = Game::create(['name' => 'Game B', 'complete' => 1, 'modPass' => 'secret']);

        $this->actingAs($admin);

        $this->post(route('admin.users.moderated-games.update', $moderator), [
            'game_ids' => [$gameA->idgame, $gameB->idgame],
        ])->assertRedirect(route('admin.users.moderated-games.edit', $moderator));

        $this->assertSame([$gameA->idgame, $gameB->idgame], $moderator->moderatedGames()->orderBy('game.idgame')->pluck('game.idgame')->all());

        $this->post(route('admin.users.moderated-games.update', $moderator), [
            'game_ids' => [$gameA->idgame],
        ]);

        $this->assertSame([$gameA->idgame], $moderator->moderatedGames()->pluck('game.idgame')->all());
    }

    public function test_admin_can_toggle_a_users_moderator_status(): void
    {
        $admin = $this->admin();
        $user = User::create(['nickname' => 'regular', 'password' => 'password123']);

        $this->actingAs($admin);

        // Making someone a moderator drops the admin straight into assigning
        // games, since a moderator with none can't edit anything yet.
        $this->post(route('admin.users.moderator.update', $user))
            ->assertRedirect(route('admin.users.moderated-games.edit', $user));
        $this->assertTrue($user->fresh()->is_moderator);

        // Revoking moderator status just returns to the user list.
        $this->post(route('admin.users.moderator.update', $user))->assertRedirect(route('admin.users.index'));
        $this->assertFalse($user->fresh()->is_moderator);
    }
}
