<?php

namespace Tests\Feature\Admin;

use App\Models\CharacterQuery;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterQueryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_user_can_add_update_and_delete_a_default_query(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $trusted = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);
        $game->moderators()->attach($trusted->iduser);

        $this->actingAs($trusted);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Add',
            'label' => '2LK starter, no meter',
            'order' => 1,
            'combo' => '2LK',
            'combolike' => '0',
        ])->assertRedirect(route('admin.queries.index', $game));

        $query = CharacterQuery::where('game_idgame', $game->idgame)->firstOrFail();
        $this->assertSame('2LK starter, no meter', $query->label);
        $this->assertSame(1, $query->order);
        $this->assertSame(['combo' => '2LK', 'combolike' => '0'], $query->filters);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Update',
            'idquery' => $query->idquery,
            'label' => '2LK starter, updated',
            'order' => 2,
            'combo' => '2LK',
            'combolike' => '0',
            'damage' => '1000',
        ])->assertRedirect(route('admin.queries.index', $game));

        $query->refresh();
        $this->assertSame('2LK starter, updated', $query->label);
        $this->assertSame(2, $query->order);
        $this->assertSame(['combo' => '2LK', 'combolike' => '0', 'damage' => '1000'], $query->filters);

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Delete',
            'idquery' => $query->idquery,
        ])->assertRedirect(route('admin.queries.index', $game));

        $this->assertDatabaseMissing('character_default_queries', ['idquery' => $query->idquery]);
    }

    public function test_non_trusted_user_cannot_manage_queries(): void
    {
        // Non-JSON 403s are converted to a redirect + flash error by the
        // app's exception handler (bootstrap/app.php), not a raw 403 body.
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $this->actingAs(User::create(['nickname' => 'regular', 'password' => 'password123']));

        $this->get(route('admin.queries.index', $game))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Add',
            'label' => 'Should not save',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('character_default_queries', ['game_idgame' => $game->idgame]);
    }

    public function test_queries_cannot_be_managed_across_games(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $foreignQuery = CharacterQuery::create([
            'game_idgame' => $otherGame->idgame,
            'label' => 'Foreign',
            'filters' => ['combo' => '5A'],
            'order' => 0,
        ]);

        $this->actingAs(User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]));

        $this->post(route('admin.queries.store', $game), [
            'action' => 'Delete',
            'idquery' => $foreignQuery->idquery,
        ]);

        $this->assertDatabaseHas('character_default_queries', ['idquery' => $foreignQuery->idquery]);
    }
}
