<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListModel;
use App\Models\TierList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
    }

    public function test_admin_can_view_the_analytics_page_with_totals_and_top_lists(): void
    {
        $game = Game::create(['name' => 'Popular Game', 'complete' => 1, 'modPass' => '', 'views' => 50]);
        $quietGame = Game::create(['name' => 'Quiet Game', 'complete' => 1, 'modPass' => '', 'views' => 5]);

        $character = Character::create(['name' => 'Star Character', 'game_idgame' => $game->idgame, 'views' => 30]);
        $type = \App\Models\GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);
        Combo::create(['combo' => 'A B C', 'character_idcharacter' => $character->idcharacter, 'type' => $type->entryid, 'views' => 20]);

        ListModel::create(['list_name' => 'Best Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1, 'views' => 15]);
        TierList::create(['title' => 'S Tier List', 'game_idgame' => $game->idgame, 'views' => 10]);

        $this->actingAs($this->admin());

        $response = $this->get(route('admin.analytics'));

        $response->assertOk();
        $response->assertSee('View Analytics');
        $response->assertSeeInOrder(['Popular Game', 'Quiet Game']);
        $response->assertSee('Star Character');
        $response->assertSee('Best Guide');
        $response->assertSee('S Tier List');
        $response->assertSee('50'); // Popular Game's view total
    }

    public function test_non_admin_cannot_view_the_analytics_page(): void
    {
        $this->actingAs(User::create(['nickname' => 'regular', 'password' => 'password123']));

        $this->get(route('admin.analytics'))->assertRedirect();
    }
}
