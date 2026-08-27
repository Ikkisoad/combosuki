<?php

namespace Tests\Feature\Security;

use App\Models\Button;
use App\Models\ButtonAlias;
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

    private ButtonAlias $buttonAlias;

    private User $user;

    private User $trustedUser;

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

        $this->buttonAlias = ButtonAlias::create([
            'alias' => 'Throw',
            'button_idbutton' => $this->button->idbutton,
            'game_idgame' => $this->game->idgame,
        ]);

        $this->user = User::create([
            'nickname' => 'regular',
            'password' => 'password123',
            'is_admin' => false,
        ]);

        $this->trustedUser = User::create([
            'nickname' => 'trusted',
            'password' => 'password123',
            'is_admin' => false,
            'trusted_user' => true,
        ]);

        $this->combo->update(['user_iduser' => $this->user->iduser]);
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
                'url' => route('admin.data-management.destroy'),
                'payload' => ['combo_ids' => [$this->combo->idcombo]],
                'assertUnchanged' => fn () => $this->assertDatabaseHas('combo', ['idcombo' => $this->combo->idcombo]),
            ],
            'admin create user' => [
                'url' => route('admin.users.store'),
                'payload' => ['nickname' => 'hacker', 'password' => 'password123', 'password_confirmation' => 'password123'],
                'assertUnchanged' => fn () => $this->assertSame(2, User::count()),
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
            'list page store' => [
                'url' => route('lists.manage.pages.store', $this->list),
                'payload' => ['Title' => 'Hacked Page'],
                'assertUnchanged' => fn () => $this->assertSame(0, $this->list->pages()->count()),
            ],
            'list category store' => [
                'url' => route('lists.manage.categories.store', $this->list),
                'payload' => ['title' => 'Hacked Category'],
                'assertUnchanged' => fn () => $this->assertSame(0, $this->list->categories()->count()),
            ],
            'list combo picker store' => [
                'url' => route('lists.manage.combos.store', $this->list),
                'payload' => ['combo_ids' => [$this->combo->idcombo]],
                'assertUnchanged' => fn () => $this->assertSame(0, $this->list->combos()->count()),
            ],
            'game settings update' => [
                'url' => route('admin.game.update', $this->game),
                'payload' => ['action' => 'Submit', 'title' => 'Hacked Game'],
                'assertUnchanged' => fn () => $this->assertSame('Test Game', $this->game->fresh()->name),
            ],
            'game store' => [
                'url' => route('games.store'),
                'payload' => ['name' => 'Hacked Game', 'image' => 'https://example.com/hacked.png'],
                'assertUnchanged' => fn () => $this->assertSame(1, Game::count()),
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
            'button alias store' => [
                'url' => route('admin.button-aliases.store', $this->game),
                'payload' => ['action' => 'Add', 'alias' => 'Evil', 'button_idbutton' => $this->button->idbutton],
                'assertUnchanged' => fn () => $this->assertSame(1, ButtonAlias::count()),
            ],
            'button alias bulk update' => [
                'url' => route('admin.button-aliases.bulkUpdate', $this->game),
                'payload' => ['aliases' => [$this->buttonAlias->idbuttonalias => ['alias' => 'Hacked', 'button_idbutton' => $this->button->idbutton]]],
                'assertUnchanged' => fn () => $this->assertSame('Throw', $this->buttonAlias->fresh()->alias),
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
            'game list bulk update' => [
                'url' => route('admin.lists.bulkUpdate', $this->game),
                'payload' => ['lists' => [$this->list->idlist => ['list_name' => 'Hacked', 'type' => 0]]],
                'assertUnchanged' => fn () => $this->assertSame('Test List', $this->list->fresh()->list_name),
            ],
            'trusted create user' => [
                'url' => route('users.store'),
                'payload' => ['nickname' => 'hacker', 'password' => 'password123', 'password_confirmation' => 'password123'],
                'assertUnchanged' => fn () => $this->assertSame(2, User::count()),
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
            'admin data management' => route('admin.data-management'),
            'admin users index' => route('admin.users.index'),
            'combo create form' => route('games.combos.create', $this->game),
            'combo edit form' => route('combos.edit', $this->combo),
            'game create form' => route('games.create'),
            'game settings edit form' => route('admin.game.edit', $this->game),
            'characters admin index' => route('admin.characters.index', $this->game),
            'links admin index' => route('admin.links.index', $this->game),
            'entries admin index' => route('admin.entries.index', $this->game),
            'buttons admin index' => route('admin.buttons.index', $this->game),
            'button aliases admin index' => route('admin.button-aliases.index', $this->game),
            'resources admin index' => route('admin.resources.index', $this->game),
            'resource values admin index' => route('admin.resources.values', [$this->game, $this->gameResource]),
            'game lists admin index' => route('admin.lists.index', $this->game),
            'create user form' => route('users.create'),
            'list manage hub' => route('lists.manage.index', $this->list),
            'list combo picker' => route('lists.manage.combos.index', $this->list),
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

        $this->get(route('admin.dashboard'))->assertRedirect()->assertSessionHas('error');
        $this->get(route('admin.data-management'))->assertRedirect()->assertSessionHas('error');
        $this->post(route('admin.data-management.destroy'), ['combo_ids' => [$this->combo->idcombo]])->assertRedirect()->assertSessionHas('error');
        $this->get(route('admin.users.index'))->assertRedirect()->assertSessionHas('error');
        $this->post(route('admin.users.store'), ['nickname' => 'hacker', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect()->assertSessionHas('error');
        $this->get(route('admin.external-sites.index'))->assertRedirect()->assertSessionHas('error');
        $this->post(route('admin.external-sites.store'), ['action' => 'Add', 'title' => 'Hacked', 'url' => 'https://hacked.example/'])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(2, User::count());
        $this->assertDatabaseHas('combo', ['idcombo' => $this->combo->idcombo]);
        $this->assertDatabaseMissing('external_site', ['title' => 'Hacked']);
    }

    public function test_authenticated_users_can_reach_the_forms_guests_were_blocked_from(): void
    {
        $this->actingAs($this->user);

        $this->get(route('games.combos.create', $this->game))->assertOk();
        $this->get(route('combos.edit', $this->combo))->assertOk();
    }

    public function test_authenticated_non_trusted_users_are_forbidden_from_trusted_only_endpoints(): void
    {
        $this->actingAs($this->user);

        $this->get(route('games.create'))->assertRedirect()->assertSessionHas('error');
        $this->post(route('games.store'), ['name' => 'Hacked Game', 'image' => 'https://example.com/hacked.png'])->assertRedirect()->assertSessionHas('error');
        $this->get(route('users.create'))->assertRedirect()->assertSessionHas('error');
        $this->post(route('users.store'), ['nickname' => 'hacker', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect()->assertSessionHas('error');
        $this->get(route('admin.game.edit', $this->game))->assertRedirect()->assertSessionHas('error');
        $this->post(route('admin.game.update', $this->game), ['action' => 'Submit', 'title' => 'Hacked Game'])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(1, Game::count());
        $this->assertSame(2, User::count());
        $this->assertSame('Test Game', $this->game->fresh()->name);
    }

    public function test_trusted_users_can_reach_trusted_only_endpoints(): void
    {
        $this->actingAs($this->trustedUser);

        $this->get(route('games.create'))->assertOk();
        $this->get(route('users.create'))->assertOk();

        $response = $this->post(route('users.store'), ['nickname' => 'plain', 'password' => 'password123', 'password_confirmation' => 'password123', 'is_admin' => 1, 'trusted_user' => 1]);
        $response->assertRedirect();

        $created = User::where('nickname', 'plain')->firstOrFail();
        $this->assertFalse($created->is_admin);
        $this->assertFalse($created->trusted_user);
    }

    /**
     * Being trusted no longer grants blanket game-edit access — a trusted
     * user who wasn't assigned to (or didn't create) a game is blocked the
     * same as any other authenticated user (see GamePolicy::update).
     */
    public function test_trusted_users_without_a_game_assignment_cannot_edit_that_game(): void
    {
        $this->actingAs($this->trustedUser);

        $this->get(route('admin.game.edit', $this->game))->assertRedirect()->assertSessionHas('error');
        $this->post(route('admin.game.update', $this->game), ['action' => 'Submit', 'title' => 'Hacked Game'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame('Test Game', $this->game->fresh()->name);
    }

    public function test_a_games_assigned_moderator_can_edit_it(): void
    {
        $this->game->moderators()->attach($this->trustedUser->iduser);

        $this->actingAs($this->trustedUser);

        $this->get(route('admin.game.edit', $this->game))->assertOk();
        $this->post(route('admin.game.update', $this->game), ['action' => 'Submit', 'title' => 'Renamed Game'])
            ->assertRedirect(route('admin.game.edit', $this->game));

        $this->assertSame('Renamed Game', $this->game->fresh()->name);
    }
}
