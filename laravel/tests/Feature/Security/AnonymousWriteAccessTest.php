<?php

namespace Tests\Feature\Security;

use App\Models\Button;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\Link;
use App\Models\ListModel;
use App\Models\ResourceValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards against the app's core authorization contract: every endpoint that
 * creates or edits content must reject anonymous requests before touching
 * the database, so a regression here (e.g. a route losing its `auth`
 * middleware) can't silently open the app up to public write access.
 */
class AnonymousWriteAccessTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Character $character;

    private Combo $combo;

    private ListModel $list;

    private GameResource $gameResource;

    private Link $link;

    private GameEntry $gameEntry;

    private Button $button;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create([
            'name' => 'Test Game',
            'complete' => 1,
            'modPass' => 'secret',
        ]);

        $this->character = Character::create([
            'name' => 'Test Character',
            'game_idgame' => $this->game->idgame,
        ]);

        $this->combo = Combo::create([
            'combo' => 'A > B > C',
            'character_idcharacter' => $this->character->idcharacter,
            'type' => 0,
        ]);

        $this->list = ListModel::create([
            'list_name' => 'Test List',
            'game_idgame' => $this->game->idgame,
            'password' => 'unused',
            'type' => 1,
        ]);

        $this->gameResource = GameResource::create([
            'game_idgame' => $this->game->idgame,
            'text_name' => 'Meter',
            'type' => 1,
            'primaryORsecundary' => 1,
        ]);

        ResourceValue::create([
            'value' => '50%',
            'order' => 1,
            'game_resources_idgame_resources' => $this->gameResource->idgame_resources,
        ]);

        $this->link = Link::create([
            'idGame' => $this->game->idgame,
            'Title' => 'Wiki',
            'Link' => 'https://example.com',
        ]);

        $this->gameEntry = GameEntry::create([
            'title' => 'Standard',
            'gameid' => $this->game->idgame,
            'order' => 1,
        ]);

        $this->button = Button::create([
            'name' => 'L',
            'color' => '#ffffff',
            'match_type' => 'exact',
            'game_idgame' => $this->game->idgame,
            'order' => 1,
        ]);

        $this->user = User::create([
            'nickname' => 'regular',
            'password' => 'password123',
            'is_admin' => false,
        ]);
    }

    /**
     * Every POST endpoint that mutates content, with a payload that would
     * succeed if an authenticated user submitted it, and an assertion that
     * proves the underlying data was left untouched.
     */
    private function writeEndpoints(): array
    {
        return [
            'admin dashboard bulk delete' => [
                'url' => route('admin.dashboard.destroy'),
                'payload' => ['combo_ids' => [$this->combo->idcombo]],
                'assertUnchanged' => fn () => $this->assertDatabaseHas('combo', ['idcombo' => $this->combo->idcombo]),
            ],
            'admin create user' => [
                'url' => route('admin.users.store'),
                'payload' => ['nickname' => 'hacker', 'password' => 'password123', 'password_confirmation' => 'password123'],
                'assertUnchanged' => fn () => $this->assertSame(1, User::count()),
            ],
            'combo store' => [
                'url' => route('games.combos.store', $this->game),
                'payload' => ['combo' => 'X > Y > Z', 'character_idcharacter' => $this->character->idcharacter, 'type' => 0],
                'assertUnchanged' => fn () => $this->assertSame(1, Combo::count()),
            ],
            'combo update' => [
                'url' => route('combos.update', $this->combo),
                'payload' => ['combo' => 'HACKED', 'character_idcharacter' => $this->character->idcharacter],
                'assertUnchanged' => fn () => $this->assertSame('A > B > C', $this->combo->fresh()->combo),
            ],
            'combo destroy' => [
                'url' => route('combos.destroy', $this->combo),
                'payload' => [],
                'assertUnchanged' => fn () => $this->assertDatabaseHas('combo', ['idcombo' => $this->combo->idcombo]),
            ],
            'list store' => [
                'url' => route('lists.store'),
                'payload' => ['list_name' => 'Hacked List', 'game_idgame' => $this->game->idgame],
                'assertUnchanged' => fn () => $this->assertSame(1, ListModel::count()),
            ],
            'list rename' => [
                'url' => route('lists.rename', $this->list),
                'payload' => ['list_name' => 'Hacked'],
                'assertUnchanged' => fn () => $this->assertSame('Test List', $this->list->fresh()->list_name),
            ],
            'list destroy' => [
                'url' => route('lists.destroy', $this->list),
                'payload' => [],
                'assertUnchanged' => fn () => $this->assertDatabaseHas('list', ['idlist' => $this->list->idlist]),
            ],
            'list entries alter' => [
                'url' => route('lists.entries.alter', $this->list),
                'payload' => ['comboid' => (string) $this->combo->idcombo, 'action' => 'Submit'],
                'assertUnchanged' => fn () => $this->assertSame(0, $this->list->combos()->count()),
            ],
            'game settings update' => [
                'url' => route('admin.game.update', $this->game),
                'payload' => ['action' => 'Submit', 'title' => 'Hacked Game'],
                'assertUnchanged' => fn () => $this->assertSame('Test Game', $this->game->fresh()->name),
            ],
            'character store' => [
                'url' => route('admin.characters.store', $this->game),
                'payload' => ['action' => 'Add', 'character' => 'Hacker'],
                'assertUnchanged' => fn () => $this->assertSame(1, Character::count()),
            ],
            'link store' => [
                'url' => route('admin.links.store', $this->game),
                'payload' => ['action' => 'Add', 'title' => 'Evil', 'link' => 'https://evil.test'],
                'assertUnchanged' => fn () => $this->assertSame(1, Link::count()),
            ],
            'game entry store' => [
                'url' => route('admin.entries.store', $this->game),
                'payload' => ['action' => 'Add', 'entry' => 'Evil'],
                'assertUnchanged' => fn () => $this->assertSame(1, GameEntry::count()),
            ],
            'button store' => [
                'url' => route('admin.buttons.store', $this->game),
                'payload' => ['action' => 'Add', 'name' => 'Evil', 'color' => '#000000', 'match_type' => 'exact'],
                'assertUnchanged' => fn () => $this->assertSame(1, Button::count()),
            ],
            'button bulk update' => [
                'url' => route('admin.buttons.bulkUpdate', $this->game),
                'payload' => ['buttons' => [$this->button->idbutton => ['name' => 'Hacked', 'color' => '#000000', 'match_type' => 'exact']]],
                'assertUnchanged' => fn () => $this->assertSame('L', $this->button->fresh()->name),
            ],
            'game resource store' => [
                'url' => route('admin.resources.store', $this->game),
                'payload' => ['action' => 'Add', 'resource' => 'Evil', 'type' => 1],
                'assertUnchanged' => fn () => $this->assertSame(1, GameResource::count()),
            ],
            'game resource value store' => [
                'url' => route('admin.resources.values.store', [$this->game, $this->gameResource]),
                'payload' => ['action' => 'EditAdd', 'resourcevalue' => 'Evil'],
                'assertUnchanged' => fn () => $this->assertSame(1, ResourceValue::count()),
            ],
            'game list store' => [
                'url' => route('admin.lists.store', $this->game),
                'payload' => ['action' => 'Delete', 'idlist' => $this->list->idlist],
                'assertUnchanged' => fn () => $this->assertDatabaseHas('list', ['idlist' => $this->list->idlist]),
            ],
        ];
    }

    /**
     * GET pages that render add/edit forms for content covered above. They
     * carry no data mutation of their own, but leaking them lets anonymous
     * visitors see admin-only editing UI and any CSRF token embedded in it.
     */
    private function protectedFormPages(): array
    {
        return [
            'admin dashboard' => route('admin.dashboard'),
            'admin users index' => route('admin.users.index'),
            'combo create form' => route('games.combos.create', $this->game),
            'combo edit form' => route('combos.edit', $this->combo),
            'game settings edit form' => route('admin.game.edit', $this->game),
            'characters admin index' => route('admin.characters.index', $this->game),
            'links admin index' => route('admin.links.index', $this->game),
            'entries admin index' => route('admin.entries.index', $this->game),
            'buttons admin index' => route('admin.buttons.index', $this->game),
            'resources admin index' => route('admin.resources.index', $this->game),
            'resource values admin index' => route('admin.resources.values', [$this->game, $this->gameResource]),
            'game lists admin index' => route('admin.lists.index', $this->game),
        ];
    }

    public function test_guests_are_redirected_and_make_no_changes_via_write_endpoints(): void
    {
        foreach ($this->writeEndpoints() as $description => $endpoint) {
            $response = $this->post($endpoint['url'], $endpoint['payload']);

            $response->assertRedirect(route('login'), "Expected guest POST to [{$description}] to redirect to login.");
            $this->assertGuest();

            ($endpoint['assertUnchanged'])();
        }
    }

    public function test_guests_are_redirected_away_from_protected_form_pages(): void
    {
        foreach ($this->protectedFormPages() as $description => $url) {
            $response = $this->get($url);

            $response->assertRedirect(route('login'), "Expected guest GET to [{$description}] to redirect to login.");
        }
    }

    public function test_guests_cannot_log_out_or_change_account_password(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('password.edit'))->assertRedirect(route('login'));
        $this->post(route('password.update'))->assertRedirect(route('login'));
    }

    public function test_authenticated_non_admin_users_are_forbidden_from_admin_only_endpoints(): void
    {
        $this->actingAs($this->user);

        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->post(route('admin.dashboard.destroy'), ['combo_ids' => [$this->combo->idcombo]])->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->post(route('admin.users.store'), ['nickname' => 'hacker', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertForbidden();

        $this->assertSame(1, User::count());
        $this->assertDatabaseHas('combo', ['idcombo' => $this->combo->idcombo]);
    }

    public function test_authenticated_users_can_reach_the_forms_guests_were_blocked_from(): void
    {
        $this->actingAs($this->user);

        $this->get(route('games.combos.create', $this->game))->assertOk();
        $this->get(route('combos.edit', $this->combo))->assertOk();
    }
}
