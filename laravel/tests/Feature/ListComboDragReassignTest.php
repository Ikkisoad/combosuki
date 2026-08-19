<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListCategory;
use App\Models\ListModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListComboDragReassignTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    private User $owner;

    private User $otherUser;

    private ListModel $list;

    private Combo $combo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->character = Character::create(['name' => 'Test Character', 'game_idgame' => $this->game->idgame]);

        $this->owner = User::create(['nickname' => 'owner', 'password' => 'password123']);
        $this->otherUser = User::create(['nickname' => 'other', 'password' => 'password123']);

        $this->list = ListModel::create([
            'list_name' => 'Test List',
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
            'user_iduser' => $this->owner->iduser,
        ]);

        $this->combo = Combo::create(['combo' => 'A > B', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0]);

        $this->list->combos()->attach($this->combo->idcombo);
    }

    private function reassign(string $url, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->patch($url, $data, ['Accept' => 'application/json']);
    }

    public function test_owner_can_reassign_a_combo_to_a_different_category(): void
    {
        $this->actingAs($this->owner);

        $category = ListCategory::create(['title' => 'Category One', 'list_idlist' => $this->list->idlist]);

        $response = $this->reassign(route('lists.entries.reassign', [$this->list, $this->combo]), [
            'list_category_idlist_category' => $category->idlist_category,
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'combo_id' => $this->combo->idcombo,
            'category_id' => $category->idlist_category,
        ]);

        $this->assertDatabaseHas('combo_listing', [
            'idcombo' => $this->combo->idcombo,
            'idlist' => $this->list->idlist,
            'list_category_idlist_category' => $category->idlist_category,
        ]);
    }

    public function test_owner_can_reassign_a_combo_back_to_no_category(): void
    {
        $this->actingAs($this->owner);

        $category = ListCategory::create(['title' => 'Category One', 'list_idlist' => $this->list->idlist]);
        $this->list->combos()->updateExistingPivot($this->combo->idcombo, ['list_category_idlist_category' => $category->idlist_category]);

        $response = $this->reassign(route('lists.entries.reassign', [$this->list, $this->combo]), [
            'list_category_idlist_category' => null,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('combo_listing', [
            'idcombo' => $this->combo->idcombo,
            'idlist' => $this->list->idlist,
            'list_category_idlist_category' => null,
        ]);
    }

    public function test_rejects_a_category_belonging_to_another_list(): void
    {
        $this->actingAs($this->owner);

        $otherList = ListModel::create([
            'list_name' => 'Other List',
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
            'user_iduser' => $this->owner->iduser,
        ]);
        $otherCategory = ListCategory::create(['title' => 'Other', 'list_idlist' => $otherList->idlist]);

        $response = $this->reassign(route('lists.entries.reassign', [$this->list, $this->combo]), [
            'list_category_idlist_category' => $otherCategory->idlist_category,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('list_category_idlist_category');
    }

    public function test_rejects_a_combo_not_in_the_list(): void
    {
        $this->actingAs($this->owner);

        $notInList = Combo::create(['combo' => 'X > Y', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0]);

        $response = $this->reassign(route('lists.entries.reassign', [$this->list, $notInList]));

        $response->assertStatus(422);
        $response->assertJson(['error' => 'That combo is not in this list.']);
    }

    public function test_non_owner_gets_a_json_403_not_a_redirect(): void
    {
        $this->actingAs($this->otherUser);

        $response = $this->reassign(route('lists.entries.reassign', [$this->list, $this->combo]));

        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_guest_gets_a_401_not_a_redirect(): void
    {
        $response = $this->reassign(route('lists.entries.reassign', [$this->list, $this->combo]));

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/json');
    }
}
