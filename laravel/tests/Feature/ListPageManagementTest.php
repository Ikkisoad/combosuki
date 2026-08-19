<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\ListCategory;
use App\Models\ListModel;
use App\Models\ListPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListPageManagementTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private User $owner;

    private User $otherUser;

    private ListModel $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

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

    public function test_owner_can_create_bulk_save_and_delete_a_page(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('lists.manage.pages.store', $this->list), [
            'Title' => 'Page One',
            'Description' => 'First page',
            'order' => 1,
        ])->assertRedirect(route('lists.manage.index', $this->list));

        $page = ListPage::where('idList', $this->list->idlist)->firstOrFail();
        $this->assertSame('Page One', $page->Title);

        $this->postJson(route('lists.manage.pages.bulk', $this->list), [
            'pages' => [
                $page->idListPage => ['Title' => 'Page One Renamed', 'Description' => null, 'order' => 2],
            ],
        ])->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame('Page One Renamed', $page->fresh()->Title);

        $this->post(route('lists.manage.pages.destroy', [$this->list, $page]))
            ->assertRedirect(route('lists.manage.index', $this->list));

        $this->assertDatabaseMissing('list_page', ['idListPage' => $page->idListPage]);
    }

    public function test_deleting_a_page_unassigns_its_categories_instead_of_deleting_them(): void
    {
        $this->actingAs($this->owner);

        $page = ListPage::create(['Title' => 'Page One', 'idList' => $this->list->idlist]);
        $category = ListCategory::create(['title' => 'Category One', 'list_idlist' => $this->list->idlist, 'idPage' => $page->idListPage]);

        $this->post(route('lists.manage.pages.destroy', [$this->list, $page]))->assertRedirect();

        $this->assertDatabaseHas('list_category', ['idlist_category' => $category->idlist_category, 'idPage' => null]);
    }

    public function test_bulk_save_updates_multiple_pages_in_one_request(): void
    {
        $this->actingAs($this->owner);

        $pageA = ListPage::create(['Title' => 'A', 'idList' => $this->list->idlist, 'order' => 1]);
        $pageB = ListPage::create(['Title' => 'B', 'idList' => $this->list->idlist, 'order' => 2]);

        $this->postJson(route('lists.manage.pages.bulk', $this->list), [
            'pages' => [
                $pageA->idListPage => ['Title' => 'A', 'Description' => null, 'order' => 5],
                $pageB->idListPage => ['Title' => 'B', 'Description' => null, 'order' => 1],
            ],
        ])->assertOk()->assertJson(['status' => 'ok']);

        $this->assertSame(5, $pageA->fresh()->order);
        $this->assertSame(1, $pageB->fresh()->order);
    }

    public function test_bulk_save_rejects_a_missing_title(): void
    {
        $this->actingAs($this->owner);

        $page = ListPage::create(['Title' => 'A', 'idList' => $this->list->idlist]);

        $this->postJson(route('lists.manage.pages.bulk', $this->list), [
            'pages' => [
                $page->idListPage => ['Title' => '', 'Description' => null, 'order' => null],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors("pages.{$page->idListPage}.Title");

        $this->assertSame('A', $page->fresh()->Title);
    }

    public function test_non_owner_cannot_manage_pages(): void
    {
        $this->actingAs($this->otherUser);

        $page = ListPage::create(['Title' => 'Page One', 'idList' => $this->list->idlist]);

        $this->post(route('lists.manage.pages.store', $this->list), ['Title' => 'Hacked'])->assertRedirect()->assertSessionHas('error');
        $this->postJson(route('lists.manage.pages.bulk', $this->list), [
            'pages' => [$page->idListPage => ['Title' => 'Hacked', 'Description' => null, 'order' => null]],
        ])->assertStatus(403);
        $this->post(route('lists.manage.pages.destroy', [$this->list, $page]))->assertRedirect()->assertSessionHas('error');
        $this->get(route('lists.manage.index', $this->list))->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('list_page', ['idListPage' => $page->idListPage, 'Title' => 'Page One']);
    }

    public function test_guest_cannot_bulk_save_pages(): void
    {
        $page = ListPage::create(['Title' => 'Page One', 'idList' => $this->list->idlist]);

        $this->postJson(route('lists.manage.pages.bulk', $this->list), [
            'pages' => [$page->idListPage => ['Title' => 'Hacked', 'Description' => null, 'order' => null]],
        ])->assertStatus(401);

        $this->assertSame('Page One', $page->fresh()->Title);
    }

    public function test_bulk_save_cannot_touch_a_page_belonging_to_another_list(): void
    {
        $this->actingAs($this->owner);

        $otherList = ListModel::create([
            'list_name' => 'Other List',
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
            'user_iduser' => $this->owner->iduser,
        ]);

        $page = ListPage::create(['Title' => 'Page One', 'idList' => $otherList->idlist]);

        $this->postJson(route('lists.manage.pages.bulk', $this->list), [
            'pages' => [$page->idListPage => ['Title' => 'Hacked', 'Description' => null, 'order' => null]],
        ])->assertOk();

        $this->assertSame('Page One', $page->fresh()->Title);

        $this->post(route('lists.manage.pages.destroy', [$this->list, $page]))->assertNotFound();
    }
}
