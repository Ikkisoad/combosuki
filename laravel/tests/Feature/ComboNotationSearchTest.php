<?php

namespace Tests\Feature;

use App\Models\Button;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboNotationSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeCombo(Game $game, string $notation): Combo
    {
        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);

        return Combo::create([
            'combo' => $notation,
            'character_idcharacter' => $character->idcharacter,
            'damage' => 0,
            'type' => 1,
        ]);
    }

    public function test_ignored_button_is_stripped_from_combo_notation_search(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'ignored' => true]);
        $combo = $this->makeCombo($game, '5A > 5B');

        $response = $this->get(route('games.combos.index', $game).'?combo=5A5B&combolike=2');

        $response->assertOk();
        $response->assertSee($combo->combo);
    }

    public function test_non_ignored_button_is_not_stripped_from_combo_notation_search(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'ignored' => false]);
        $combo = $this->makeCombo($game, '5A > 5B');

        $response = $this->get(route('games.combos.index', $game).'?combo=5A5B&combolike=2');

        $response->assertOk();
        $response->assertSee('0 result(s)');
    }
}
