<?php

namespace Tests\Feature\Admin;

use App\Models\Character;
use App\Models\Game;
use App\Models\ListModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameListBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_edit_form_renders_inputs_for_every_guide_bound_to_the_shared_form(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $listA = ListModel::create(['list_name' => 'Guide A', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);
        $listB = ListModel::create(['list_name' => 'Guide B', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 2]);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $listA->featuredForCharacters()->attach($character->idcharacter);

        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $response = $this->get(route('admin.lists.index', $game));

        $response->assertOk();
        $response->assertSee('id="bulk-lists-form"', false);
        $response->assertSee("lists[{$listA->idlist}][list_name]", false);
        $response->assertSee("lists[{$listB->idlist}][list_name]", false);
        $response->assertSee("lists[{$listA->idlist}][characters][]", false);
        $response->assertSee('Valentine');
        $response->assertSee("<option value=\"{$character->idcharacter}\" selected", false);
        $response->assertSee('Save All');
    }

    public function test_bulk_update_saves_every_submitted_guide_in_one_request(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $listA = ListModel::create(['list_name' => 'Guide A', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);
        $listB = ListModel::create(['list_name' => 'Guide B', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);

        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $response = $this->post(route('admin.lists.bulkUpdate', $game), [
            'lists' => [
                $listA->idlist => ['list_name' => 'Renamed A', 'type' => 3],
                $listB->idlist => ['list_name' => 'Renamed B', 'type' => 0],
            ],
        ]);

        $response->assertRedirect(route('admin.lists.index', $game));

        $this->assertDatabaseHas('list', ['idlist' => $listA->idlist, 'list_name' => 'Renamed A', 'type' => 3]);
        $this->assertDatabaseHas('list', ['idlist' => $listB->idlist, 'list_name' => 'Renamed B', 'type' => 0]);
    }

    public function test_bulk_update_cannot_touch_guides_belonging_to_another_game(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $foreignList = ListModel::create(['list_name' => 'Foreign Guide', 'game_idgame' => $otherGame->idgame, 'password' => 'secret', 'type' => 1]);

        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $this->post(route('admin.lists.bulkUpdate', $game), [
            'lists' => [
                $foreignList->idlist => ['list_name' => 'Hacked', 'type' => 0],
            ],
        ]);

        $this->assertSame('Foreign Guide', $foreignList->fresh()->list_name);
    }

    public function test_bulk_update_features_a_guide_for_the_selected_characters(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $list = ListModel::create(['list_name' => 'Guide A', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);

        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $this->post(route('admin.lists.bulkUpdate', $game), [
            'lists' => [
                $list->idlist => ['list_name' => 'Guide A', 'type' => 1, 'characters' => [$character->idcharacter]],
            ],
        ]);

        $this->assertDatabaseHas('character_featured_guide', [
            'list_idlist' => $list->idlist,
            'character_idcharacter' => $character->idcharacter,
        ]);
    }

    public function test_bulk_update_unfeatures_a_guide_when_no_characters_are_submitted(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Valentine', 'game_idgame' => $game->idgame]);
        $list = ListModel::create(['list_name' => 'Guide A', 'game_idgame' => $game->idgame, 'password' => 'secret', 'type' => 1]);
        $list->featuredForCharacters()->attach($character->idcharacter);

        $this->actingAs(User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]));

        $this->post(route('admin.lists.bulkUpdate', $game), [
            'lists' => [
                $list->idlist => ['list_name' => 'Guide A', 'type' => 1],
            ],
        ]);

        $this->assertDatabaseMissing('character_featured_guide', [
            'list_idlist' => $list->idlist,
            'character_idcharacter' => $character->idcharacter,
        ]);
    }
}
