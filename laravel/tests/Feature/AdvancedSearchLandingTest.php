<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedSearchLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_advanced_search_with_no_query_shows_the_form_without_running_a_search(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);
        $combo = Combo::create([
            'combo' => '5A > 5B',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 0,
            'type' => 1,
        ]);

        $response = $this->get(route('games.combos.index', $game));

        $response->assertOk();
        $response->assertViewHas('combos', null);
        $response->assertDontSee($combo->combo);
        $response->assertSee('Fill in the filters above and click Search.');
    }

    public function test_view_all_combos_link_marker_still_runs_the_search_immediately(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);
        $combo = Combo::create([
            'combo' => '5A > 5B',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 0,
            'type' => 1,
        ]);

        $response = $this->get(route('games.combos.index', $game).'?search=1');

        $response->assertOk();
        $response->assertViewHas('combos', function ($combos) use ($combo) {
            return $combos !== null && $combos->pluck('idcombo')->contains($combo->idcombo);
        });
    }

    public function test_deleting_a_combo_redirects_to_the_search_results_not_the_blank_form(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);
        $trustedUser = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);

        $combo = Combo::create([
            'combo' => '5A > 5B',
            'character_idcharacter' => $character->idcharacter,
            'damage' => 0,
            'type' => 1,
        ]);

        $response = $this->actingAs($trustedUser)->post(route('combos.destroy', $combo));

        $response->assertRedirect(route('games.combos.index', ['game' => $game, 'search' => 1]));

        $this->followRedirects($response)
            ->assertOk()
            ->assertDontSee('Fill in the filters above and click Search.');
    }
}
