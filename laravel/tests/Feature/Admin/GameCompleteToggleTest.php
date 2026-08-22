<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCompleteToggleTest extends TestCase
{
    use RefreshDatabase;

    private function game(int $complete): Game
    {
        return Game::create(['name' => 'Test Fighter', 'complete' => $complete, 'modPass' => '']);
    }

    private function admin(): User
    {
        return User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
    }

    public function test_admin_can_mark_a_game_as_complete(): void
    {
        $game = $this->game(0);

        $this->actingAs($this->admin());

        $this->post(route('admin.game.update', $game), ['action' => 'Complete'])
            ->assertRedirect(route('admin.game.edit', $game))
            ->assertSessionHas('status');

        $this->assertSame(1, $game->fresh()->complete);
    }

    public function test_admin_can_mark_a_game_as_incomplete(): void
    {
        $game = $this->game(1);

        $this->actingAs($this->admin());

        $this->post(route('admin.game.update', $game), ['action' => 'Incomplete'])
            ->assertRedirect(route('admin.game.edit', $game));

        $this->assertSame(0, $game->fresh()->complete);
    }

    public function test_toggling_completeness_preserves_the_lock_state(): void
    {
        $this->actingAs($this->admin());

        $locked = $this->game(-1);
        $this->post(route('admin.game.update', $locked), ['action' => 'Complete']);
        $this->assertSame(2, $locked->fresh()->complete);

        $lockedComplete = $this->game(2);
        $this->post(route('admin.game.update', $lockedComplete), ['action' => 'Incomplete']);
        $this->assertSame(-1, $lockedComplete->fresh()->complete);
    }

    public function test_the_completeness_button_is_only_rendered_for_admins(): void
    {
        $game = $this->game(0);

        $this->actingAs($this->admin());
        $this->get(route('admin.game.edit', $game))->assertOk()->assertSee('Mark Complete');

        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));
        $this->get(route('admin.game.edit', $game))->assertOk()->assertDontSee('Mark Complete');
    }

    public function test_trusted_non_admin_cannot_change_completeness(): void
    {
        $game = $this->game(0);

        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('admin.game.update', $game), ['action' => 'Complete'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, $game->fresh()->complete);
    }
}
