<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\EditHistory;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\ListCategory;
use App\Models\ListModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    private GameEntry $listingType;

    private User $admin;

    private User $owner;

    private Combo $combo;

    private ListModel $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->character = Character::create(['name' => 'Test Character', 'game_idgame' => $this->game->idgame]);
        $this->listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $this->game->idgame, 'order' => 1]);

        $this->admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $this->owner = User::create(['nickname' => 'owner', 'password' => 'password123']);

        $this->combo = Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
            'user_iduser' => $this->owner->iduser,
        ]);

        $this->list = ListModel::create([
            'list_name' => 'Test List',
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
            'user_iduser' => $this->owner->iduser,
        ]);
    }

    public function test_game_edits_record_history_against_the_game(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.game.update', $this->game), ['action' => 'Submit', 'title' => 'Renamed Game'])
            ->assertRedirect(route('admin.game.edit', $this->game));

        $this->post(route('admin.game.update', $this->game), ['action' => 'Lock'])
            ->assertRedirect(route('admin.game.edit', $this->game));

        $histories = EditHistory::where('editable_type', Game::class)->where('editable_id', $this->game->idgame)->get();

        $this->assertCount(2, $histories);
        foreach ($histories as $history) {
            $this->assertSame($this->admin->iduser, $history->user_iduser);
            $this->assertSame('updated', $history->action);
            $this->assertNotNull($history->created_at);
        }
    }

    public function test_game_delete_records_history_before_removal(): void
    {
        $this->actingAs($this->admin);
        $gameId = $this->game->idgame;

        $this->post(route('admin.game.update', $this->game), ['action' => 'Delete'])
            ->assertRedirect(route('games.index'));

        $history = EditHistory::where('editable_type', Game::class)->where('editable_id', $gameId)->first();

        $this->assertNotNull($history);
        $this->assertSame('deleted', $history->action);
        $this->assertSame($this->admin->iduser, $history->user_iduser);
    }

    public function test_combo_update_records_history_against_the_combo(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('combos.update', $this->combo), [
            'character_idcharacter' => $this->character->idcharacter,
            'listingtype' => $this->listingType->entryid,
            'combo' => 'A > B > C edited',
        ])->assertRedirect(route('combos.show', $this->combo));

        $history = $this->combo->editHistories()->first();

        $this->assertNotNull($history);
        $this->assertSame('updated', $history->action);
        $this->assertSame($this->owner->iduser, $history->user_iduser);
    }

    public function test_combo_destroy_records_history_before_removal(): void
    {
        $this->actingAs($this->owner);
        $comboId = $this->combo->idcombo;

        $this->post(route('combos.destroy', $this->combo))->assertRedirect();

        $history = EditHistory::where('editable_type', Combo::class)->where('editable_id', $comboId)->first();

        $this->assertNotNull($history);
        $this->assertSame('deleted', $history->action);
        $this->assertSame($this->owner->iduser, $history->user_iduser);
    }

    public function test_list_rename_records_history_against_the_list(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('lists.rename', $this->list), ['list_name' => 'Renamed List'])->assertRedirect();

        $history = $this->list->editHistories()->first();

        $this->assertNotNull($history);
        $this->assertSame('updated', $history->action);
        $this->assertSame($this->owner->iduser, $history->user_iduser);
    }

    public function test_list_destroy_records_history_before_removal(): void
    {
        $this->actingAs($this->owner);
        $listId = $this->list->idlist;

        $this->post(route('lists.destroy', $this->list))->assertRedirect();

        $history = EditHistory::where('editable_type', ListModel::class)->where('editable_id', $listId)->first();

        $this->assertNotNull($history);
        $this->assertSame('deleted', $history->action);
    }

    public function test_list_entry_alter_and_reassign_record_against_the_list(): void
    {
        $this->actingAs($this->owner);
        $this->list->combos()->attach($this->combo->idcombo);
        $category = ListCategory::create(['title' => 'Category', 'list_idlist' => $this->list->idlist]);

        $this->patch(route('lists.entries.reassign', [$this->list, $this->combo]), [
            'list_category_idlist_category' => $category->idlist_category,
        ], ['Accept' => 'application/json'])->assertOk();

        $this->post(route('lists.entries.alter', $this->list), ['comboid' => (string) $this->combo->idcombo])
            ->assertRedirect();

        $this->assertSame(2, $this->list->editHistories()->count());
        $this->assertTrue($this->list->editHistories()->get()->every(fn ($h) => $h->editable_type === ListModel::class));
    }

    public function test_list_page_and_category_mutations_record_against_the_list(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('lists.manage.pages.store', $this->list), ['Title' => 'Page 1'])->assertRedirect();
        $page = $this->list->pages()->firstOrFail();

        $this->post(route('lists.manage.pages.bulk', $this->list), [
            'pages' => [$page->idListPage => ['Title' => 'Page 1 renamed', 'order' => 1]],
        ])->assertOk();

        $this->post(route('lists.manage.categories.store', $this->list), ['title' => 'Category 1'])->assertRedirect();
        $category = $this->list->categories()->firstOrFail();

        $this->post(route('lists.manage.categories.bulk', $this->list), [
            'categories' => [$category->idlist_category => ['title' => 'Category 1 renamed', 'order' => 1]],
        ])->assertOk();

        $this->post(route('lists.manage.pages.destroy', [$this->list, $page]))->assertRedirect();
        $this->post(route('lists.manage.categories.destroy', [$this->list, $category]))->assertRedirect();

        $histories = $this->list->editHistories()->get();

        $this->assertCount(6, $histories);
        $this->assertTrue($histories->every(fn ($h) => $h->editable_type === ListModel::class && $h->user_iduser === $this->owner->iduser));
    }

    public function test_list_bulk_add_combos_records_a_single_history_entry(): void
    {
        $this->actingAs($this->owner);
        $otherCombo = Combo::create([
            'combo' => 'X > Y',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => $this->listingType->entryid,
        ]);

        $this->post(route('lists.manage.combos.store', $this->list), ['combo_ids' => [$otherCombo->idcombo]])
            ->assertRedirect();

        $this->assertSame(1, $this->list->editHistories()->count());
    }

    public function test_list_bulk_add_with_nothing_added_does_not_record_history(): void
    {
        $this->actingAs($this->owner);
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherCharacter = Character::create(['name' => 'Other Character', 'game_idgame' => $otherGame->idgame]);
        $mismatchedCombo = Combo::create(['combo' => 'X > Y', 'character_idcharacter' => $otherCharacter->idcharacter, 'type' => 0]);

        $this->post(route('lists.manage.combos.store', $this->list), ['combo_ids' => [$mismatchedCombo->idcombo]])
            ->assertRedirect();

        $this->assertSame(0, $this->list->editHistories()->count());
    }

    public function test_game_edit_page_shows_history_and_empty_state(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.game.edit', $this->game))->assertOk()->assertSee('No edits recorded yet.');

        $this->game->recordEdit();

        $this->get(route('admin.game.edit', $this->game))->assertOk()->assertSee('Updated by '.$this->admin->nickname);
    }

    public function test_combo_edit_page_shows_history_and_empty_state(): void
    {
        $this->actingAs($this->owner);

        $this->get(route('combos.edit', $this->combo))->assertOk()->assertSee('No edits recorded yet.');

        $this->combo->recordEdit();

        $this->get(route('combos.edit', $this->combo))->assertOk()->assertSee('Updated by '.$this->owner->nickname);
    }

    public function test_list_manage_page_shows_history_and_empty_state(): void
    {
        $this->actingAs($this->owner);

        $this->get(route('lists.manage.index', $this->list))->assertOk()->assertSee('No edits recorded yet.');

        $this->list->recordEdit();

        $this->get(route('lists.manage.index', $this->list))->assertOk()->assertSee('Updated by '.$this->owner->nickname);
    }
}
