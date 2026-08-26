<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameDamageStatsTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_average_damage_and_top_character_per_default_query(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $valentine = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $painwheel = Character::create(['name' => 'Painwheel', 'game_idgame' => $game->idgame]);

        $query = CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '2LK starter',
            'filters' => ['combo' => '2LK', 'combolike' => '0'],
            'order' => 0,
        ]);

        Combo::create([
            'combo' => '2LK > 236B', 'character_idcharacter' => $valentine->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => 1,
        ]);
        Combo::create([
            'combo' => '2LK > 5C', 'character_idcharacter' => $painwheel->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1,
        ]);

        $response = $this->get(route('games.tabs.damage-stats', $game));

        $response->assertOk();
        $response->assertSee($query->label);
        // Query tab: highest damage is Valentine's 300, average across the two characters is 200.
        $response->assertSee('200');
        $response->assertSeeInOrder(['Highest Damage', 'Valentine', '300']);
    }

    public function test_query_pane_shows_no_data_message_when_no_combo_matches(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '5C starter',
            'filters' => ['combo' => '5C', 'combolike' => '0'],
            'order' => 0,
        ]);

        $response = $this->get(route('games.tabs.damage-stats', $game));

        $response->assertOk();
        $response->assertSee('5C starter');
        $response->assertSee('Not enough combo data yet.');
    }
}
