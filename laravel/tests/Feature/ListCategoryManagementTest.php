<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListCategory;
use App\Models\ListModel;
use App\Models\ListPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListCategoryManagementTest extends TestCase
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

    public function test_owner_can_create_bulk_save_and_delete_a_category(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('lists.manage.categories.store', $this->list), [
            'title' => 'Category One',
            'description' => 'Combos that use meter.',
        ])->assertRedirect(route('lists.manage.index', $this->list));

        $category = ListCategory::where('list_idlist', $this->list->idlist)->firstOrFail();
        $this->assertSame('Category One', $category->title);
        $this->assertSame('Combos that use meter.', $category->description);
        $this->assertNull($category->idPage);

        $this->postJson(route('lists.manage.categories.bulk', $this->list), [
            'categories' => [
                $category->idlist_category => ['title' => 'Renamed', 'description' => 'Updated description.', 'idPage' => null, 'order' => null],
            ],
        ])->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame('Renamed', $category->fresh()->title);
        $this->assertSame('Updated description.', $category->fresh()->description);

        $this->post(route('lists.manage.categories.destroy', [$this->list, $category]))
            ->assertRedirect(route('lists.manage.index', $this->list));

        $this->assertDatabaseMissing('list_category', ['idlist_category' => $category->idlist_category]);
    }

    public function test_assigning_a_category_to_a_page_persists(): void
    {
        $this->actingAs($this->owner);

        $page = ListPage::create(['Title' => 'Page One', 'idList' => $this->list->idlist]);
        $category = ListCategory::create(['title' => 'Category One', 'list_idlist' => $this->list->idlist]);

        $this->postJson(route('lists.manage.categories.bulk', $this->list), [
            'categories' => [
                $category->idlist_category => ['title' => 'Category One', 'idPage' => $page->idListPage, 'order' => null],
            ],
        ])->assertOk();

        $this->assertSame($page->idListPage, $category->fresh()->idPage);
    }

    public function test_bulk_save_rejects_an_idpage_from_another_list(): void
    {
        $this->actingAs($this->owner);

        $otherList = ListModel::create([
            'list_name' => 'Other List',
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
            'user_iduser' => $this->owner->iduser,
        ]);
        $otherPage = ListPage::create(['Title' => 'Other Page', 'idList' => $otherList->idlist]);
        $category = ListCategory::create(['title' => 'Category One', 'list_idlist' => $this->list->idlist]);

        $this->postJson(route('lists.manage.categories.bulk', $this->list), [
            'categories' => [
                $category->idlist_category => ['title' => 'Category One', 'idPage' => $otherPage->idListPage, 'order' => null],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors("categories.{$category->idlist_category}.idPage");

        $this->assertNull($category->fresh()->idPage);
    }

    public function test_idpage_must_belong_to_the_same_list(): void
    {
        $this->actingAs($this->owner);

        $otherList = ListModel::create([
            'list_name' => 'Other List',
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
            'user_iduser' => $this->owner->iduser,
        ]);
        $otherPage = ListPage::create(['Title' => 'Other Page', 'idList' => $otherList->idlist]);

        $this->post(route('lists.manage.categories.store', $this->list), [
            'title' => 'Category One',
            'idPage' => $otherPage->idListPage,
        ])->assertSessionHasErrors('idPage');

        $this->assertDatabaseMissing('list_category', ['title' => 'Category One']);
    }

    public function test_deleting_a_category_uncategorizes_its_combos_instead_of_removing_them(): void
    {
        $this->actingAs($this->owner);

        $category = ListCategory::create(['title' => 'Category One', 'list_idlist' => $this->list->idlist]);

        $combo = Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => 0,
        ]);

        $this->list->combos()->attach($combo->idcombo, ['list_category_idlist_category' => $category->idlist_category]);

        $this->post(route('lists.manage.categories.destroy', [$this->list, $category]))->assertRedirect();

        $this->assertDatabaseHas('combo_listing', [
            'idcombo' => $combo->idcombo,
            'idlist' => $this->list->idlist,
            'list_category_idlist_category' => null,
        ]);
    }

    public function test_category_description_is_shown_on_the_public_list_page(): void
    {
        $category = ListCategory::create([
            'title' => 'Category One',
            'description' => 'Combos that use meter.',
            'list_idlist' => $this->list->idlist,
        ]);

        $combo = Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => 0,
        ]);

        $this->list->combos()->attach($combo->idcombo, ['list_category_idlist_category' => $category->idlist_category]);

        $this->get(route('lists.show', $this->list))->assertSee('Combos that use meter.');
    }

    public function test_guest_cannot_bulk_save_categories(): void
    {
        $category = ListCategory::create(['title' => 'Category One', 'list_idlist' => $this->list->idlist]);

        $this->postJson(route('lists.manage.categories.bulk', $this->list), [
            'categories' => [$category->idlist_category => ['title' => 'Hacked', 'idPage' => null, 'order' => null]],
        ])->assertStatus(401);

        $this->assertSame('Category One', $category->fresh()->title);
    }

    public function test_non_owner_cannot_manage_categories(): void
    {
        $this->actingAs($this->otherUser);

        $category = ListCategory::create(['title' => 'Category One', 'list_idlist' => $this->list->idlist]);

        $this->post(route('lists.manage.categories.store', $this->list), ['title' => 'Hacked'])->assertRedirect()->assertSessionHas('error');
        $this->postJson(route('lists.manage.categories.bulk', $this->list), [
            'categories' => [$category->idlist_category => ['title' => 'Hacked', 'idPage' => null, 'order' => null]],
        ])->assertStatus(403);
        $this->post(route('lists.manage.categories.destroy', [$this->list, $category]))->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('list_category', ['idlist_category' => $category->idlist_category, 'title' => 'Category One']);
    }
}
