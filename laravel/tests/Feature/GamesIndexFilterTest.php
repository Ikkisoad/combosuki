<?php

namespace Tests\Feature;

use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamesIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Game::create(['name' => 'Street Fighter 6', 'complete' => 1, 'modPass' => 'secret']);
        Game::create(['name' => 'Guilty Gear Strive', 'complete' => 1, 'modPass' => 'secret']);
    }

    public function test_index_lists_all_games_without_a_search_term(): void
    {
        $this->get(route('games.index'))
            ->assertOk()
            ->assertSee('Street Fighter 6')
            ->assertSee('Guilty Gear Strive');
    }

    public function test_index_filters_games_by_name(): void
    {
        $this->get(route('games.index', ['name' => 'street']))
            ->assertOk()
            ->assertSee('Street Fighter 6')
            ->assertDontSee('Guilty Gear Strive');
    }

    public function test_index_shows_empty_state_when_no_game_matches(): void
    {
        $this->get(route('games.index', ['name' => 'no such game']))
            ->assertOk()
            ->assertSee('No games found.')
            ->assertDontSee('Street Fighter 6')
            ->assertDontSee('Guilty Gear Strive');
    }
}
