<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListModel;
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
        // Extra higher-damage combos push $weakMatch out of the character's top-3 "Top Damage Combos" section.
        Combo::create([
            'combo' => '5C > 236C', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 250, 'type' => 1,
        ]);
        Combo::create([
            'combo' => '2LK > 214B', 'character_idcharacter' => $character->idcharacter, 'submited' => now(), 'damage' => 200, 'type' => 1,
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

    public function test_a_query_scoped_to_specific_characters_only_appears_on_their_pages(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $toph = Character::create(['name' => 'Toph', 'game_idgame' => $game->idgame]);
        $katara = Character::create(['name' => 'Katara', 'game_idgame' => $game->idgame]);
        $aang = Character::create(['name' => 'Aang', 'game_idgame' => $game->idgame]);

        $query = CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => 'Command grab',
            'filters' => ['combo' => '622', 'combolike' => '0'],
            'order' => 0,
        ]);
        $query->characters()->attach([$toph->idcharacter, $katara->idcharacter]);

        $tophResponse = $this->get(route('characters.show', [$game, $toph]));
        $tophResponse->assertOk();
        $tophResponse->assertSee('Command grab');

        $kataraResponse = $this->get(route('characters.show', [$game, $katara]));
        $kataraResponse->assertOk();
        $kataraResponse->assertSee('Command grab');

        $aangResponse = $this->get(route('characters.show', [$game, $aang]));
        $aangResponse->assertOk();
        $aangResponse->assertDontSee('Command grab');
    }

    public function test_character_belonging_to_a_different_game_404s(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $otherGame->idgame]);

        $this->get(route('characters.show', [$game, $character]))->assertNotFound();
    }

    public function test_character_page_shows_guides_featured_for_that_character(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $guide = ListModel::create(['list_name' => 'Valentine Bread and Butter', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);
        $character->featuredGuides()->attach($guide->idlist);

        $response = $this->get(route('characters.show', [$game, $character]));

        $response->assertOk();
        $response->assertSee('Featured Guides');
        $response->assertSee(route('lists.show', $guide), false);
    }

    public function test_character_page_does_not_show_a_hidden_guide_even_if_featured(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);

        $hiddenGuide = ListModel::create(['list_name' => 'Hidden Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 0]);
        $character->featuredGuides()->attach($hiddenGuide->idlist);

        $response = $this->get(route('characters.show', [$game, $character]));

        $response->assertOk();
        $response->assertDontSee('Featured Guides');
    }

    public function test_character_page_does_not_show_a_guide_featured_for_a_different_character(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $otherCharacter = Character::create(['name' => 'Painwheel', 'game_idgame' => $game->idgame]);

        $guide = ListModel::create(['list_name' => 'Painwheel Guide', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);
        $otherCharacter->featuredGuides()->attach($guide->idlist);

        $response = $this->get(route('characters.show', [$game, $character]));

        $response->assertOk();
        $response->assertDontSee('Featured Guides');
    }
}
