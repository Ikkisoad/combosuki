<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_page_shows_the_highest_damage_combo_matching_each_query(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame, 'image' => 'character-portraits/valentine.jpg']);
        $otherCharacter = Character::create(['name' => 'Painwheel', 'game_idgame' => $game->idgame]);

        $query = CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '2LK starter',
            'filters' => ['combo' => '2LK', 'combolike' => '0'],
            'order' => 0,
        ]);

        $weakMatch = Combo::create([
            'combo' => '2LK > 5B', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 100, 'type' => 1,
        ]);
        $bestMatch = Combo::create([
            'combo' => '2LK > 236B', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 300, 'type' => 1,
        ]);
        Combo::create([
            'combo' => '2LK > 5C', 'character_idcharacter' => $otherCharacter->idcharacter, 'submited' => now(), 'damage' => 999, 'type' => 1,
        ]);

        $response = $this->get(route('characters.show', [$game, $character]));

        $response->assertOk();
        $response->assertSee('Valentine');
        $response->assertSee('2LK starter');
        $response->assertSee(route('combos.show', $bestMatch), false);
        $response->assertDontSee(route('combos.show', $weakMatch), false);
    }

    public function test_character_page_shows_no_combo_found_when_a_query_has_no_match(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => '5C starter',
            'filters' => ['combo' => '5C', 'combolike' => '0'],
            'order' => 0,
        ]);

        $response = $this->get(route('characters.show', [$game, $character]));

        $response->assertOk();
        $response->assertSee('No combo found yet', false);
    }

    public function test_character_belonging_to_a_different_game_404s(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $otherGame->idgame]);

        $this->get(route('characters.show', [$game, $character]))->assertNotFound();
    }
}
