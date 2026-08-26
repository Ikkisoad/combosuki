<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\ListModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameGuidesTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_featured_guide_appears_only_in_featured_section(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        ListModel::create(['list_name' => 'Featured Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 3]);
        ListModel::create(['list_name' => 'Normal Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);

        $response = $this->get(route('games.tabs.guides', $game));

        $response->assertOk();
        $response->assertSeeInOrder(['Featured Guides', 'Featured Guide', 'Guides', 'Normal Guide']);
    }

    public function test_empty_states_render_when_no_guides_exist(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $response = $this->get(route('games.tabs.guides', $game));

        $response->assertOk();
        $response->assertSee('No featured guides for this game yet.');
        $response->assertSee('No guides for this game yet.');
    }
}
