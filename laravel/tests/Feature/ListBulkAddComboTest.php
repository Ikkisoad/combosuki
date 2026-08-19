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

class ListBulkAddComboTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    private User $owner;

    private User $otherUser;

    private ListModel $list;

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
    }

    public function test_owner_can_bulk_add_selected_combos_into_a_category(): void
    {
        $this->actingAs($this->owner);

        $category = ListCategory::create(['title' => 'Category One', 'list_idlist' => $this->list->idlist]);

        $comboA = Combo::create(['combo' => 'A > B', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0]);
        $comboB = Combo::create(['combo' => 'C > D', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0]);

        $this->post(route('lists.manage.combos.store', $this->list), [
            'combo_ids' => [$comboA->idcombo, $comboB->idcombo],
            'category_id' => $category->idlist_category,
        ])->assertRedirect(route('lists.manage.combos.index', $this->list));

        $this->assertDatabaseHas('combo_listing', [
            'idcombo' => $comboA->idcombo,
            'idlist' => $this->list->idlist,
            'list_category_idlist_category' => $category->idlist_category,
        ]);
        $this->assertDatabaseHas('combo_listing', [
            'idcombo' => $comboB->idcombo,
            'idlist' => $this->list->idlist,
            'list_category_idlist_category' => $category->idlist_category,
        ]);
    }

    public function test_combos_from_a_different_game_are_silently_skipped(): void
    {
        $this->actingAs($this->owner);

        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherCharacter = Character::create(['name' => 'Other Character', 'game_idgame' => $otherGame->idgame]);
        $mismatchedCombo = Combo::create(['combo' => 'X > Y', 'character_idcharacter' => $otherCharacter->idcharacter, 'type' => 0]);

        $this->post(route('lists.manage.combos.store', $this->list), [
            'combo_ids' => [$mismatchedCombo->idcombo],
        ])->assertRedirect();

        $this->assertSame(0, $this->list->combos()->count());
    }

    public function test_picker_requires_update_policy(): void
    {
        $this->actingAs($this->otherUser);

        $this->get(route('lists.manage.combos.index', $this->list))->assertRedirect()->assertSessionHas('error');

        $combo = Combo::create(['combo' => 'A > B', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0]);
        $this->post(route('lists.manage.combos.store', $this->list), ['combo_ids' => [$combo->idcombo]])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, $this->list->combos()->count());
    }

    public function test_picker_shows_fallback_message_for_a_gameless_list(): void
    {
        $this->actingAs($this->owner);

        $gamelessList = ListModel::create([
            'list_name' => 'Gameless List',
            'password' => 'unused',
            'type' => 1,
            'user_iduser' => $this->owner->iduser,
        ]);

        $response = $this->get(route('lists.manage.combos.index', $gamelessList));

        $response->assertOk();
        $response->assertViewHas('needsGame', true);
    }
}
