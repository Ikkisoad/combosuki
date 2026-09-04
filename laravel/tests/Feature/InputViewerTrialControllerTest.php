<?php

namespace Tests\Feature;

use App\Models\Button;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputViewerTrialControllerTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    private ListModel $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->character = Character::create(['name' => 'Valentine', 'game_idgame' => $this->game->idgame]);

        $this->list = ListModel::create([
            'list_name' => 'Valentine BnBs',
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
        ]);
    }

    public function test_search_guides_matches_by_name(): void
    {
        $response = $this->getJson(route('input-viewer.guides.search').'?q=Valentine');

        $response->assertOk();
        $response->assertJson(['guides' => [
            ['idlist' => $this->list->idlist, 'list_name' => 'Valentine BnBs', 'game_name' => 'Test Game'],
        ]]);
    }

    public function test_search_guides_excludes_names_that_do_not_match(): void
    {
        $response = $this->getJson(route('input-viewer.guides.search').'?q=Nonexistent');

        $response->assertOk();
        $response->assertExactJson(['guides' => []]);
    }

    public function test_search_guides_scoped_to_a_game_lists_them_alphabetically(): void
    {
        $zList = ListModel::create([
            'list_name' => 'Z Guide', 'game_idgame' => $this->game->idgame, 'password' => 'unused', 'type' => 1,
        ]);

        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        ListModel::create([
            'list_name' => 'A Guide From Another Game', 'game_idgame' => $otherGame->idgame, 'password' => 'unused', 'type' => 1,
        ]);

        $response = $this->getJson(route('input-viewer.guides.search').'?game_idgame='.$this->game->idgame);

        $response->assertOk();
        $response->assertExactJson(['guides' => [
            ['idlist' => $this->list->idlist, 'list_name' => 'Valentine BnBs', 'game_name' => 'Test Game'],
            ['idlist' => $zList->idlist, 'list_name' => 'Z Guide', 'game_name' => 'Test Game'],
        ]]);
    }

    public function test_guide_combos_returns_combos_attached_to_the_list(): void
    {
        $combo = Combo::create([
            'combo' => '5LK', 'character_idcharacter' => $this->character->idcharacter,
            'submited' => now(), 'damage' => 200, 'type' => 1,
        ]);
        $this->list->combos()->attach($combo->idcombo);

        $response = $this->getJson(route('input-viewer.guides.combos', $this->list));

        $response->assertOk();
        $response->assertJson(['combos' => [
            ['idcombo' => $combo->idcombo, 'character_name' => 'Valentine', 'damage' => 200],
        ]]);
    }

    public function test_guide_combos_excludes_combos_not_attached_to_the_list(): void
    {
        Combo::create([
            'combo' => '5LK', 'character_idcharacter' => $this->character->idcharacter,
            'submited' => now(), 'damage' => 200, 'type' => 1,
        ]);

        $response = $this->getJson(route('input-viewer.guides.combos', $this->list));

        $response->assertOk();
        $response->assertExactJson(['combos' => []]);
    }

    public function test_guide_combos_respects_combo_visibility(): void
    {
        $author = User::create(['nickname' => 'someone', 'password' => 'password123']);

        // Unverified combo by a user with no other verified combo: hidden from guests.
        $hiddenCombo = Combo::create([
            'combo' => '5LK', 'character_idcharacter' => $this->character->idcharacter, 'user_iduser' => $author->iduser,
            'submited' => now(), 'damage' => 200, 'type' => 1, 'verified' => 0,
        ]);
        $this->list->combos()->attach($hiddenCombo->idcombo);

        $response = $this->getJson(route('input-viewer.guides.combos', $this->list));

        $response->assertOk();
        $response->assertExactJson(['combos' => []]);
    }

    public function test_combo_moves_returns_the_ordered_move_list(): void
    {
        Button::create(['name' => '2LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $this->game->idgame, 'order' => 1]);
        Button::create(['name' => '5LK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $this->game->idgame, 'order' => 2]);
        Button::create(['name' => '>', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $this->game->idgame, 'order' => 3, 'ignored' => true]);

        $combo = Combo::create([
            'combo' => '2LK > 5LK', 'character_idcharacter' => $this->character->idcharacter,
            'submited' => now(), 'damage' => 100, 'type' => 1,
        ]);

        $response = $this->getJson(route('input-viewer.combos.moves', $combo));

        $response->assertOk();
        $response->assertJson([
            'idcombo' => $combo->idcombo,
            'character_name' => 'Valentine',
            'moves' => [
                ['key' => '2lk', 'label' => '2LK', 'color' => '#ff0000'],
                ['key' => '5lk', 'label' => '5LK', 'color' => '#00ff00'],
            ],
        ]);
    }
}
