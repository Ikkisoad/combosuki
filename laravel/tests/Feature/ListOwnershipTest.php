<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\ListModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private User $owner;

    private User $otherUser;

    private User $trustedUser;

    private ListModel $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $this->owner = User::create(['nickname' => 'owner', 'password' => 'password123']);
        $this->otherUser = User::create(['nickname' => 'other', 'password' => 'password123']);
        $this->trustedUser = User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]);

        $this->list = ListModel::create([
            'list_name' => 'Test List',
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
            'user_iduser' => $this->owner->iduser,
        ]);
    }

    public function test_owner_can_rename_and_delete_their_own_list(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('lists.rename', $this->list), ['list_name' => 'Renamed'])
            ->assertRedirect(route('lists.show', $this->list));
        $this->assertSame('Renamed', $this->list->fresh()->list_name);

        $this->post(route('lists.destroy', $this->list))->assertRedirect(route('lists.index'));
        $this->assertDatabaseMissing('list', ['idlist' => $this->list->idlist]);
    }

    public function test_non_owner_cannot_rename_or_delete_someone_elses_list(): void
    {
        $this->actingAs($this->otherUser);

        $this->post(route('lists.rename', $this->list), ['list_name' => 'Hacked'])->assertRedirect()->assertSessionHas('error');
        $this->post(route('lists.destroy', $this->list))->assertRedirect()->assertSessionHas('error');

        $this->assertSame('Test List', $this->list->fresh()->list_name);
        $this->assertDatabaseHas('list', ['idlist' => $this->list->idlist]);
    }

    public function test_non_owner_cannot_alter_entries_of_someone_elses_list(): void
    {
        $this->actingAs($this->otherUser);

        $this->post(route('lists.entries.alter', $this->list), ['comboid' => '1', 'action' => 'Submit'])
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_trusted_user_can_rename_and_delete_any_list(): void
    {
        $this->actingAs($this->trustedUser);

        $this->post(route('lists.rename', $this->list), ['list_name' => 'Renamed by moderator'])
            ->assertRedirect(route('lists.show', $this->list));
        $this->assertSame('Renamed by moderator', $this->list->fresh()->list_name);

        $this->post(route('lists.destroy', $this->list))->assertRedirect(route('lists.index'));
        $this->assertDatabaseMissing('list', ['idlist' => $this->list->idlist]);
    }

    public function test_owner_can_reach_the_management_hub(): void
    {
        $this->actingAs($this->owner);

        $this->get(route('lists.manage.index', $this->list))->assertOk();
    }

    public function test_non_owner_cannot_reach_the_management_hub(): void
    {
        $this->actingAs($this->otherUser);

        $this->get(route('lists.manage.index', $this->list))->assertRedirect()->assertSessionHas('error');
    }

    public function test_trusted_user_can_reach_the_management_hub(): void
    {
        $this->actingAs($this->trustedUser);

        $this->get(route('lists.manage.index', $this->list))->assertOk();
    }

    public function test_deleting_a_list_also_deletes_its_pages(): void
    {
        $this->actingAs($this->owner);

        $page = \App\Models\ListPage::create(['Title' => 'Page One', 'idList' => $this->list->idlist]);

        $this->post(route('lists.destroy', $this->list))->assertRedirect(route('lists.index'));

        $this->assertDatabaseMissing('list_page', ['idListPage' => $page->idListPage]);
    }
}
