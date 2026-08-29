<?php

namespace Tests\Feature;

use App\Models\Button;
use App\Models\ButtonAlias;
use App\Models\Character;
use App\Models\CharacterButtonAlias;
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

    private function throwAlias(Game $game): ButtonAlias
    {
        $button = Button::create(['name' => 'LP+LK', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame]);

        return ButtonAlias::create(['alias' => 'Throw', 'button_idbutton' => $button->idbutton, 'game_idgame' => $game->idgame]);
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

    public function test_searching_an_alias_word_finds_combos_stored_with_its_button_notation(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->throwAlias($game);
        $combo = $this->makeCombo($game, '5A > LP+LK');

        $response = $this->get(route('games.combos.index', $game).'?combo=Throw&combolike=2');

        $response->assertOk();
        $response->assertSee($combo->combo);
    }

    public function test_searching_an_alias_word_is_case_insensitive(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->throwAlias($game);
        $combo = $this->makeCombo($game, 'LP+LK');

        $response = $this->get(route('games.combos.index', $game).'?combo=throw&combolike=2');

        $response->assertOk();
        $response->assertSee($combo->combo);
    }

    public function test_searching_the_button_notation_finds_combos_stored_with_the_alias_word(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->throwAlias($game);
        $combo = $this->makeCombo($game, '5A > Throw');

        $response = $this->get(route('games.combos.index', $game).'?combo=LP%2BLK&combolike=2');

        $response->assertOk();
        $response->assertSee($combo->combo);
    }

    public function test_alias_expansion_combines_with_ignored_token_stripping(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'ignored' => true]);
        $this->throwAlias($game);
        $combo = $this->makeCombo($game, '5A > LP+LK');

        $response = $this->get(route('games.combos.index', $game).'?combo=5AThrow&combolike=2');

        $response->assertOk();
        $response->assertSee($combo->combo);
    }

    public function test_searching_a_character_alias_word_finds_that_characters_combo_when_the_character_is_selected(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Toph', 'game_idgame' => $game->idgame]);
        $button = Button::create(['name' => '236A', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame]);
        CharacterButtonAlias::create(['alias' => 'Tackle', 'button_idbutton' => $button->idbutton, 'character_idcharacter' => $character->idcharacter]);
        $combo = Combo::create(['combo' => '236A', 'character_idcharacter' => $character->idcharacter, 'damage' => 0, 'type' => 1]);

        $response = $this->get(route('games.combos.index', $game).'?combo=Tackle&combolike=2&characterid='.$character->idcharacter);

        $response->assertOk();
        $response->assertSee($combo->combo);
    }

    public function test_searching_a_character_alias_word_finds_nothing_when_no_character_is_selected(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Toph', 'game_idgame' => $game->idgame]);
        $button = Button::create(['name' => '236A', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame]);
        CharacterButtonAlias::create(['alias' => 'Tackle', 'button_idbutton' => $button->idbutton, 'character_idcharacter' => $character->idcharacter]);
        Combo::create(['combo' => '236A', 'character_idcharacter' => $character->idcharacter, 'damage' => 0, 'type' => 1]);

        $response = $this->get(route('games.combos.index', $game).'?combo=Tackle&combolike=2');

        $response->assertOk();
        $response->assertSee('0 result(s)');
    }

    public function test_searching_a_character_alias_word_finds_nothing_for_a_different_selected_character(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $toph = Character::create(['name' => 'Toph', 'game_idgame' => $game->idgame]);
        $aang = Character::create(['name' => 'Aang', 'game_idgame' => $game->idgame]);
        $button = Button::create(['name' => '236A', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame]);
        CharacterButtonAlias::create(['alias' => 'Tackle', 'button_idbutton' => $button->idbutton, 'character_idcharacter' => $toph->idcharacter]);
        Combo::create(['combo' => '236A', 'character_idcharacter' => $toph->idcharacter, 'damage' => 0, 'type' => 1]);

        $response = $this->get(route('games.combos.index', $game).'?combo=Tackle&combolike=2&characterid='.$aang->idcharacter);

        $response->assertOk();
        $response->assertSee('0 result(s)');
    }
}
