<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListModel;
use App\Models\ListPage;
use App\Models\ListPageCanvasEdge;
use App\Models\ListPageCanvasNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListCanvasManagementTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    private User $owner;

    private User $otherUser;

    private ListModel $list;

    private ListPage $canvasPage;

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

        $this->canvasPage = ListPage::create([
            'Title' => 'Canvas Page',
            'idList' => $this->list->idlist,
            'page_type' => 'canvas',
        ]);
    }

    public function test_owner_can_create_a_text_node_a_combo_node_and_an_edge_between_them(): void
    {
        $this->actingAs($this->owner);

        $combo = Combo::create(['combo' => 'A > B', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0]);

        $textResponse = $this->postJson(route('lists.manage.canvas.nodes.store', [$this->list, $this->canvasPage]), [
            'node_type' => 'text',
            'title' => 'Route Start',
            'body' => 'Begin here.',
            'pos_x' => 10,
            'pos_y' => 20,
        ])->assertCreated();

        $textNodeId = $textResponse->json('node.idCanvasNode');
        $this->assertDatabaseHas('list_page_canvas_node', [
            'idCanvasNode' => $textNodeId,
            'idListPage' => $this->canvasPage->idListPage,
            'node_type' => 'text',
            'title' => 'Route Start',
        ]);

        $comboResponse = $this->postJson(route('lists.manage.canvas.nodes.store', [$this->list, $this->canvasPage]), [
            'node_type' => 'combo',
            'idCombo' => $combo->idcombo,
            'pos_x' => 100,
            'pos_y' => 20,
        ])->assertCreated();

        $comboNodeId = $comboResponse->json('node.idCanvasNode');
        $this->assertDatabaseHas('list_page_canvas_node', [
            'idCanvasNode' => $comboNodeId,
            'node_type' => 'combo',
            'idCombo' => $combo->idcombo,
        ]);

        $this->postJson(route('lists.manage.canvas.edges.store', [$this->list, $this->canvasPage]), [
            'idFromNode' => $textNodeId,
            'idToNode' => $comboNodeId,
            'label' => 'then',
        ])->assertCreated();

        $this->assertDatabaseHas('list_page_canvas_edge', [
            'idFromNode' => $textNodeId,
            'idToNode' => $comboNodeId,
            'label' => 'then',
        ]);
    }

    public function test_updating_a_node_position_does_not_touch_title_or_combo(): void
    {
        $this->actingAs($this->owner);

        $combo = Combo::create(['combo' => 'A > B', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0]);
        $node = ListPageCanvasNode::create([
            'idListPage' => $this->canvasPage->idListPage,
            'node_type' => 'combo',
            'idCombo' => $combo->idcombo,
            'pos_x' => 0,
            'pos_y' => 0,
        ]);

        $this->patchJson(route('lists.manage.canvas.nodes.update', [$this->list, $this->canvasPage, $node]), [
            'pos_x' => 42.5,
            'pos_y' => 7,
            'title' => 'Should be ignored',
        ])->assertOk();

        $node->refresh();
        $this->assertSame(42.5, $node->pos_x);
        $this->assertSame(7.0, $node->pos_y);
        $this->assertNull($node->title);
        $this->assertSame($combo->idcombo, $node->idCombo);
    }

    public function test_combo_node_creation_is_rejected_when_the_combo_belongs_to_a_different_game(): void
    {
        $this->actingAs($this->owner);

        $otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);
        $otherCharacter = Character::create(['name' => 'Other Character', 'game_idgame' => $otherGame->idgame]);
        $mismatchedCombo = Combo::create(['combo' => 'X > Y', 'character_idcharacter' => $otherCharacter->idcharacter, 'type' => 0]);

        $this->postJson(route('lists.manage.canvas.nodes.store', [$this->list, $this->canvasPage]), [
            'node_type' => 'combo',
            'idCombo' => $mismatchedCombo->idcombo,
        ])->assertStatus(422)->assertJsonValidationErrors('idCombo');

        $this->assertSame(0, ListPageCanvasNode::count());
    }

    public function test_deleting_a_node_cascades_its_edges(): void
    {
        $this->actingAs($this->owner);

        $nodeA = ListPageCanvasNode::create(['idListPage' => $this->canvasPage->idListPage, 'node_type' => 'text', 'title' => 'A']);
        $nodeB = ListPageCanvasNode::create(['idListPage' => $this->canvasPage->idListPage, 'node_type' => 'text', 'title' => 'B']);
        $edge = ListPageCanvasEdge::create(['idFromNode' => $nodeA->idCanvasNode, 'idToNode' => $nodeB->idCanvasNode]);

        $this->postJson(route('lists.manage.canvas.nodes.destroy', [$this->list, $this->canvasPage, $nodeA]))->assertOk();

        $this->assertDatabaseMissing('list_page_canvas_node', ['idCanvasNode' => $nodeA->idCanvasNode]);
        $this->assertDatabaseMissing('list_page_canvas_edge', ['idCanvasEdge' => $edge->idCanvasEdge]);
    }

    public function test_non_owner_and_guest_are_rejected_on_every_mutation_route(): void
    {
        $node = ListPageCanvasNode::create(['idListPage' => $this->canvasPage->idListPage, 'node_type' => 'text', 'title' => 'A']);

        $this->actingAs($this->otherUser);

        $this->postJson(route('lists.manage.canvas.nodes.store', [$this->list, $this->canvasPage]), [
            'node_type' => 'text', 'title' => 'Hacked',
        ])->assertStatus(403);
        $this->patchJson(route('lists.manage.canvas.nodes.update', [$this->list, $this->canvasPage, $node]), ['title' => 'Hacked'])->assertStatus(403);
        $this->postJson(route('lists.manage.canvas.nodes.destroy', [$this->list, $this->canvasPage, $node]))->assertStatus(403);

        $this->assertDatabaseHas('list_page_canvas_node', ['idCanvasNode' => $node->idCanvasNode, 'title' => 'A']);

        auth()->logout();

        $this->postJson(route('lists.manage.canvas.nodes.store', [$this->list, $this->canvasPage]), [
            'node_type' => 'text', 'title' => 'Hacked',
        ])->assertStatus(401);
    }

    public function test_cannot_touch_a_node_belonging_to_another_lists_page(): void
    {
        $this->actingAs($this->owner);

        $otherList = ListModel::create([
            'list_name' => 'Other List',
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
            'user_iduser' => $this->owner->iduser,
        ]);
        $otherPage = ListPage::create(['Title' => 'Other Canvas', 'idList' => $otherList->idlist, 'page_type' => 'canvas']);
        $node = ListPageCanvasNode::create(['idListPage' => $otherPage->idListPage, 'node_type' => 'text', 'title' => 'Other']);

        $this->patchJson(route('lists.manage.canvas.nodes.update', [$this->list, $this->canvasPage, $node]), ['title' => 'Hacked'])
            ->assertNotFound();

        $this->assertSame('Other', $node->fresh()->title);
    }

    public function test_show_renders_the_canvas_partial_for_a_canvas_page_in_html_and_json(): void
    {
        ListPageCanvasNode::create(['idListPage' => $this->canvasPage->idListPage, 'node_type' => 'text', 'title' => 'A']);

        $htmlResponse = $this->get(route('lists.show', $this->list).'?page='.$this->canvasPage->idListPage);
        $htmlResponse->assertOk();
        $htmlResponse->assertSee('list-canvas-view', false);

        $jsonResponse = $this->getJson(route('lists.show', $this->list).'?page='.$this->canvasPage->idListPage);
        $jsonResponse->assertOk();
        $this->assertStringContainsString('list-canvas-view', $jsonResponse->json('content'));
    }

    public function test_combo_search_includes_combos_already_attached_to_the_list(): void
    {
        $this->actingAs($this->owner);

        $combo = Combo::create(['combo' => 'A > B', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0]);
        $this->list->combos()->attach($combo->idcombo);

        $response = $this->getJson(route('lists.manage.canvas.combos.search', [$this->list, $this->canvasPage]));

        $response->assertOk();
        $this->assertContains($combo->idcombo, collect($response->json('combos'))->pluck('idcombo')->all());
    }

    public function test_combo_search_can_be_restricted_to_combos_already_in_the_guide(): void
    {
        $this->actingAs($this->owner);

        $inGuide = Combo::create(['combo' => 'A > B', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0]);
        $notInGuide = Combo::create(['combo' => 'C > D', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0]);
        $this->list->combos()->attach($inGuide->idcombo);

        $response = $this->getJson(route('lists.manage.canvas.combos.search', [$this->list, $this->canvasPage]).'?only_in_guide=1');

        $response->assertOk();
        $ids = collect($response->json('combos'))->pluck('idcombo')->all();
        $this->assertContains($inGuide->idcombo, $ids);
        $this->assertNotContains($notInGuide->idcombo, $ids);
    }

    public function test_combo_search_can_be_filtered_by_character(): void
    {
        $this->actingAs($this->owner);

        $otherCharacter = Character::create(['name' => 'Other Character', 'game_idgame' => $this->game->idgame]);
        $matching = Combo::create(['combo' => 'A > B', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0]);
        $other = Combo::create(['combo' => 'C > D', 'character_idcharacter' => $otherCharacter->idcharacter, 'type' => 0]);

        $response = $this->getJson(route('lists.manage.canvas.combos.search', [$this->list, $this->canvasPage]).'?characterid='.$this->character->idcharacter);

        $response->assertOk();
        $ids = collect($response->json('combos'))->pluck('idcombo')->all();
        $this->assertContains($matching->idcombo, $ids);
        $this->assertNotContains($other->idcombo, $ids);
    }
}
