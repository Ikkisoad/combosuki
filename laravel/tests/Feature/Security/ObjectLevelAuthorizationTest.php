<?php

namespace Tests\Feature\Security;

use App\Models\Game;
use App\Models\GameResource;
use App\Models\ListCategory;
use App\Models\ListModel;
use App\Models\ListPage;
use App\Models\ListPageCanvasEdge;
use App\Models\ListPageCanvasNode;
use App\Models\ResourceValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The list-management routes are nested: list -> page -> node/edge, and
 * list -> category. Authorization is checked on the *parent* only
 * ($this->authorize('update', $list)); the child is bound independently by
 * the router, and the sole thing tying the two together is the
 * abort_if($child->idList !== $list->idlist, 404) line immediately after.
 *
 * That makes this the app's classic IDOR surface. Every test here acts as an
 * attacker who *legitimately owns their own list* — so the authorize() call
 * always passes and the request can only be stopped by the binding check.
 * Remove that line, or let a refactor drop it, and owning any one list would
 * mean being able to mutate the children of every other list.
 *
 * ListCanvasManagementTest covers one of these actions; this covers the rest,
 * plus the equivalent shape in the per-game admin area, where the attacker is
 * a real moderator of one game reaching sideways into another.
 */
class ObjectLevelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;

    private User $victim;

    private ListModel $attackerList;

    private ListPage $attackerPage;

    private ListModel $victimList;

    private ListPage $victimPage;

    private ListPageCanvasNode $victimNodeA;

    private ListPageCanvasNode $victimNodeB;

    private ListPageCanvasEdge $victimEdge;

    private ListCategory $victimCategory;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        $this->attacker = User::create(['nickname' => 'attacker', 'password' => 'password123']);
        $this->victim = User::create(['nickname' => 'victim', 'password' => 'password123']);

        [$this->attackerList, $this->attackerPage] = $this->makeListWithCanvas($this->attacker, 'Attacker List');
        [$this->victimList, $this->victimPage] = $this->makeListWithCanvas($this->victim, 'Victim List');

        $this->victimNodeA = ListPageCanvasNode::create([
            'idListPage' => $this->victimPage->idListPage,
            'node_type' => 'text',
            'title' => 'Victim Node A',
            'pos_x' => 0,
            'pos_y' => 0,
        ]);

        $this->victimNodeB = ListPageCanvasNode::create([
            'idListPage' => $this->victimPage->idListPage,
            'node_type' => 'text',
            'title' => 'Victim Node B',
            'pos_x' => 50,
            'pos_y' => 0,
        ]);

        $this->victimEdge = ListPageCanvasEdge::create([
            'idFromNode' => $this->victimNodeA->idCanvasNode,
            'idToNode' => $this->victimNodeB->idCanvasNode,
            'label' => 'original',
        ]);

        $victimTextPage = ListPage::create([
            'Title' => 'Victim Text Page',
            'idList' => $this->victimList->idlist,
            'page_type' => 'text',
        ]);

        $this->victimCategory = ListCategory::create([
            'title' => 'Victim Category',
            'list_idlist' => $this->victimList->idlist,
            'idPage' => $victimTextPage->idListPage,
            'order' => 1,
        ]);

        $this->actingAs($this->attacker);
    }

    /**
     * @return array{0: ListModel, 1: ListPage}
     */
    private function makeListWithCanvas(User $owner, string $name): array
    {
        $list = ListModel::create([
            'list_name' => $name,
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
            'user_iduser' => $owner->iduser,
        ]);

        $page = ListPage::create([
            'Title' => $name.' Canvas',
            'idList' => $list->idlist,
            'page_type' => 'canvas',
        ]);

        return [$list, $page];
    }

    public function test_canvas_node_store_rejects_a_page_belonging_to_another_list(): void
    {
        $this->postJson(route('lists.manage.canvas.nodes.store', [$this->attackerList, $this->victimPage]), [
            'node_type' => 'text',
            'title' => 'Injected',
            'pos_x' => 0,
            'pos_y' => 0,
        ])->assertNotFound();

        $this->assertDatabaseMissing('list_page_canvas_node', ['title' => 'Injected']);
    }

    public function test_canvas_node_update_rejects_a_node_belonging_to_another_lists_page(): void
    {
        $this->patchJson(route('lists.manage.canvas.nodes.update', [
            $this->attackerList, $this->victimPage, $this->victimNodeA,
        ]), ['title' => 'Hijacked'])->assertNotFound();

        $this->assertSame('Victim Node A', $this->victimNodeA->fresh()->title);
    }

    public function test_canvas_node_destroy_rejects_a_node_belonging_to_another_lists_page(): void
    {
        $this->postJson(route('lists.manage.canvas.nodes.destroy', [
            $this->attackerList, $this->victimPage, $this->victimNodeA,
        ]))->assertNotFound();

        $this->assertDatabaseHas('list_page_canvas_node', ['idCanvasNode' => $this->victimNodeA->idCanvasNode]);
    }

    public function test_canvas_edge_store_rejects_a_page_belonging_to_another_list(): void
    {
        $this->postJson(route('lists.manage.canvas.edges.store', [$this->attackerList, $this->victimPage]), [
            'idFromNode' => $this->victimNodeA->idCanvasNode,
            'idToNode' => $this->victimNodeB->idCanvasNode,
            'label' => 'injected',
        ])->assertNotFound();

        $this->assertDatabaseMissing('list_page_canvas_edge', ['label' => 'injected']);
    }

    /**
     * The second half of the edge-store guard: even with a page the attacker
     * legitimately owns, the node ids are constrained to that page's own
     * nodes by Rule::in — so foreign nodes fail validation rather than
     * silently wiring a cross-list edge.
     */
    public function test_canvas_edge_store_cannot_wire_nodes_from_another_lists_page(): void
    {
        $this->postJson(route('lists.manage.canvas.edges.store', [$this->attackerList, $this->attackerPage]), [
            'idFromNode' => $this->victimNodeA->idCanvasNode,
            'idToNode' => $this->victimNodeB->idCanvasNode,
            'label' => 'injected',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('list_page_canvas_edge', ['label' => 'injected']);
    }

    public function test_canvas_edge_update_rejects_an_edge_from_another_lists_page(): void
    {
        $this->patchJson(route('lists.manage.canvas.edges.update', [
            $this->attackerList, $this->victimPage, $this->victimEdge,
        ]), ['label' => 'hijacked'])->assertNotFound();

        $this->assertSame('original', $this->victimEdge->fresh()->label);
    }

    public function test_canvas_edge_destroy_rejects_an_edge_from_another_lists_page(): void
    {
        $this->postJson(route('lists.manage.canvas.edges.destroy', [
            $this->attackerList, $this->victimPage, $this->victimEdge,
        ]))->assertNotFound();

        $this->assertDatabaseHas('list_page_canvas_edge', ['idCanvasEdge' => $this->victimEdge->idCanvasEdge]);
    }

    public function test_page_destroy_rejects_a_page_belonging_to_another_list(): void
    {
        $this->post(route('lists.manage.pages.destroy', [$this->attackerList, $this->victimPage]))
            ->assertNotFound();

        $this->assertDatabaseHas('list_page', ['idListPage' => $this->victimPage->idListPage]);
    }

    public function test_category_destroy_rejects_a_category_belonging_to_another_list(): void
    {
        $this->post(route('lists.manage.categories.destroy', [$this->attackerList, $this->victimCategory]))
            ->assertNotFound();

        $this->assertDatabaseHas('list_category', ['idlist_category' => $this->victimCategory->idlist_category]);
    }

    /**
     * The same nesting problem one level up, and the more interesting version
     * of it: the per-game admin routes are gated by can:update,game, which a
     * game moderator legitimately passes for their own game. The resource is
     * bound separately, so only GameResourceController's own game-binding
     * checks stop them reaching a neighbouring game's data through their own.
     *
     * The existing Admin/* cross-game tests all run as a full admin, which
     * exercises the abort_if but never this scenario — a real, correctly
     * scoped tenant pivoting sideways.
     */
    public function test_a_game_moderator_cannot_reach_another_games_resource_through_its_own_game(): void
    {
        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);

        $moderator = User::create(['nickname' => 'gamemod', 'password' => 'password123', 'is_moderator' => true]);
        $moderator->moderatedGames()->attach($this->game->idgame);

        $foreignResource = GameResource::create([
            'game_idgame' => $otherGame->idgame,
            'text_name' => 'Other Meter',
            'type' => 1,
            'primaryORsecundary' => 1,
        ]);

        $foreignValue = ResourceValue::create([
            'value' => 'untouched',
            'order' => 1,
            'game_resources_idgame_resources' => $foreignResource->idgame_resources,
        ]);

        $this->actingAs($moderator);

        $this->get(route('admin.resources.values', [$this->game, $foreignResource]))->assertNotFound();

        $this->post(route('admin.resources.values.store', [$this->game, $foreignResource]), [
            'value' => 'injected',
            'order' => 2,
        ])->assertNotFound();

        $this->assertDatabaseMissing('resources_values', ['value' => 'injected']);
        $this->assertSame('untouched', $foreignValue->fresh()->value);
    }
}
