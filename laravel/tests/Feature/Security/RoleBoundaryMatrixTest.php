<?php

namespace Tests\Feature\Security;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Access in this app is decided by three independent boolean columns plus a
 * per-game pivot, and they deliberately do not nest cleanly:
 *
 *   User::isTrusted()   = is_admin || trusted_user || is_moderator
 *   User::isModerator() = is_admin || is_moderator
 *   GamePolicy::update  = is_admin || moderatedGames pivot
 *
 * So a trusted user gets staff powers over combos, lists and matches but is
 * deliberately *not* enough to edit a game; a moderator gets two specific
 * user-management carve-outs but not the admin dashboard; and a game
 * moderator gets full editing rights over their assigned games only.
 *
 * Individual routes are tested piecemeal across the rest of the suite, but
 * nothing asserts the grid as a whole — which means a route moved between the
 * `admin`, `moderator`, `trusted` and `can:update,game` groups can widen
 * access without any single existing test failing. That is what this file is
 * for: every role against every representative route, in one place.
 *
 * Note the naming trap it also pins down: routes named admin.* are covered by
 * two different gates. admin.dashboard and friends require true is_admin,
 * while the forty-odd per-game admin.* routes only require can:update,game.
 */
class RoleBoundaryMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Game $assignedGame;

    private Game $otherGame;

    /** @var array<string, User|null> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assignedGame = Game::create(['name' => 'Assigned Game', 'complete' => 1, 'modPass' => 'secret']);
        $this->otherGame = Game::create(['name' => 'Other Game', 'complete' => 1, 'modPass' => 'secret']);

        $gameModerator = User::create(['nickname' => 'gamemod', 'password' => 'password123', 'is_moderator' => true]);
        $gameModerator->moderatedGames()->attach($this->assignedGame->idgame);

        $this->users = [
            'guest' => null,
            'plain' => User::create(['nickname' => 'plain', 'password' => 'password123']),
            'trusted' => User::create(['nickname' => 'trusted', 'password' => 'password123', 'trusted_user' => true]),
            'moderator' => User::create(['nickname' => 'moderator', 'password' => 'password123', 'is_moderator' => true]),
            'gameModerator' => $gameModerator,
            'admin' => User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]),
        ];
    }

    /**
     * Route key => [HTTP method, URL]. Kept out of the data provider because
     * providers run before setUp(), so no model (and therefore no route
     * needing a bound game) exists yet.
     *
     * @return array{0: string, 1: string}
     */
    private function urlFor(string $routeKey): array
    {
        return match ($routeKey) {
            // Gate: `admin` — true site administrator.
            'admin.dashboard' => ['get', route('admin.dashboard')],
            'admin.data-management' => ['get', route('admin.data-management')],
            'admin.settings.edit' => ['get', route('admin.settings.edit')],
            'admin.analytics' => ['get', route('admin.analytics')],
            'admin.external-sites.index' => ['get', route('admin.external-sites.index')],
            'admin.donation.edit' => ['get', route('admin.donation.edit')],
            'admin.users.moderated-games.edit' => ['get', route('admin.users.moderated-games.edit', $this->users['plain'])],

            // Gate: `moderator` — the user-management carve-outs.
            'admin.users.index' => ['get', route('admin.users.index')],

            // Gate: `trusted`.
            'games.create' => ['get', route('games.create')],
            'users.create' => ['get', route('users.create')],

            // Gate: can:update,game, on the game the game-moderator owns.
            'admin.game.edit' => ['get', route('admin.game.edit', $this->assignedGame)],
            'admin.characters.index' => ['get', route('admin.characters.index', $this->assignedGame)],
            'admin.buttons.index' => ['get', route('admin.buttons.index', $this->assignedGame)],
            'admin.resources.index' => ['get', route('admin.resources.index', $this->assignedGame)],
            'admin.lists.index' => ['get', route('admin.lists.index', $this->assignedGame)],
            'admin.unverified-combos.index' => ['get', route('admin.unverified-combos.index', $this->assignedGame)],

            // The same six on a game the game-moderator is NOT assigned to.
            'other.admin.game.edit' => ['get', route('admin.game.edit', $this->otherGame)],
            'other.admin.characters.index' => ['get', route('admin.characters.index', $this->otherGame)],
            'other.admin.buttons.index' => ['get', route('admin.buttons.index', $this->otherGame)],
            'other.admin.resources.index' => ['get', route('admin.resources.index', $this->otherGame)],
            'other.admin.lists.index' => ['get', route('admin.lists.index', $this->otherGame)],
            'other.admin.unverified-combos.index' => ['get', route('admin.unverified-combos.index', $this->otherGame)],
        };
    }

    /**
     * Every combination is listed explicitly rather than derived, so that
     * reading this table tells you the intended policy without having to
     * re-run the role helpers in your head.
     *
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function accessMatrix(): iterable
    {
        $adminOnly = [
            'admin.dashboard',
            'admin.data-management',
            'admin.settings.edit',
            'admin.analytics',
            'admin.external-sites.index',
            'admin.donation.edit',
            'admin.users.moderated-games.edit',
        ];

        $gameScoped = [
            'admin.game.edit',
            'admin.characters.index',
            'admin.buttons.index',
            'admin.resources.index',
            'admin.lists.index',
            'admin.unverified-combos.index',
        ];

        $allowedBy = [
            ...array_fill_keys($adminOnly, ['admin']),
            // Moderators get the user list; admins do too (isModerator()).
            'admin.users.index' => ['moderator', 'gameModerator', 'admin'],
            // isTrusted() is admin || trusted_user || is_moderator.
            'games.create' => ['trusted', 'moderator', 'gameModerator', 'admin'],
            'users.create' => ['trusted', 'moderator', 'gameModerator', 'admin'],
            // GamePolicy::update is is_admin || the pivot — trusted alone
            // is deliberately not enough.
            ...array_fill_keys($gameScoped, ['gameModerator', 'admin']),
            ...array_fill_keys(
                array_map(fn ($key) => 'other.'.$key, $gameScoped),
                ['admin']
            ),
        ];

        $roles = ['guest', 'plain', 'trusted', 'moderator', 'gameModerator', 'admin'];

        foreach ($allowedBy as $routeKey => $allowedRoles) {
            foreach ($roles as $role) {
                yield "{$role} -> {$routeKey}" => [$role, $routeKey, in_array($role, $allowedRoles, true)];
            }
        }
    }

    #[DataProvider('accessMatrix')]
    public function test_each_route_allows_exactly_the_expected_roles(string $role, string $routeKey, bool $allowed): void
    {
        [$method, $url] = $this->urlFor($routeKey);

        $user = $this->users[$role];

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->{$method}($url);

        if ($allowed) {
            $response->assertOk();

            return;
        }

        if ($user === null) {
            $response->assertRedirect(route('login'));

            return;
        }

        // An authenticated-but-unauthorized request does not get a 403 page:
        // bootstrap/app.php renders non-JSON 403s as a redirect carrying a
        // flashed error instead.
        $response->assertRedirect()->assertSessionHas('error');
    }

    /**
     * Stated as its own test, separate from the matrix, because it is the
     * rule most likely to be "simplified" by someone who assumes trusted is
     * simply a lower-privileged admin.
     */
    public function test_being_trusted_alone_never_grants_game_editing(): void
    {
        $this->actingAs($this->users['trusted']);

        foreach ([$this->assignedGame, $this->otherGame] as $game) {
            $this->get(route('admin.game.edit', $game))->assertRedirect()->assertSessionHas('error');

            $this->post(route('admin.game.update', $game), ['name' => 'Renamed by trusted'])
                ->assertRedirect()->assertSessionHas('error');
        }

        $this->assertSame('Assigned Game', $this->assignedGame->fresh()->name);
        $this->assertSame('Other Game', $this->otherGame->fresh()->name);
    }

    public function test_a_game_moderator_cannot_write_to_a_game_they_are_not_assigned_to(): void
    {
        $this->actingAs($this->users['gameModerator']);

        $this->post(route('admin.game.update', $this->otherGame), ['name' => 'Hijacked'])
            ->assertRedirect()->assertSessionHas('error');

        $this->post(route('admin.characters.store', $this->otherGame), ['name' => 'Injected'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame('Other Game', $this->otherGame->fresh()->name);
        $this->assertDatabaseMissing('character', ['name' => 'Injected']);
    }

    /**
     * The moderator carve-out is exactly two routes wide. Anything else on
     * the admin dashboard must stay closed to them, or "moderator" quietly
     * becomes a second admin role.
     */
    public function test_a_moderator_gets_only_the_user_management_carve_outs(): void
    {
        $this->actingAs($this->users['moderator']);

        $this->get(route('admin.users.index'))->assertOk();

        $this->post(route('admin.users.trusted.update', $this->users['plain']))->assertRedirect();

        foreach ([
            route('admin.dashboard'),
            route('admin.settings.edit'),
            route('admin.analytics'),
            route('admin.data-management'),
        ] as $url) {
            $this->get($url)->assertRedirect()->assertSessionHas('error');
        }

        // Creating users and promoting to moderator stay admin-only.
        $this->post(route('admin.users.store'), [
            'nickname' => 'created-by-moderator',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('user', ['nickname' => 'created-by-moderator']);

        $this->post(route('admin.users.moderator.update', $this->users['plain']))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertFalse((bool) $this->users['plain']->fresh()->is_moderator);
    }
}
