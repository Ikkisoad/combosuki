<?php

namespace Tests\Feature\Security;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\ListModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * AuthController regenerates the session id on login and invalidates the
 * session plus its CSRF token on logout. Neither was asserted anywhere.
 * Losing the login-time regenerate() reintroduces session fixation: an
 * attacker who can plant a known session id — via a sibling subdomain cookie,
 * or an XSS anywhere on the origin — keeps that id after the victim
 * authenticates, and with it the victim's session.
 *
 * Also covers the two disclosure channels around credentials: the login
 * failure path (which must not distinguish a nonexistent account from a
 * Discord-only one from a wrong password, in message *or* timing) and the
 * response bodies, which must never carry a password hash or a game's stored
 * passwords regardless of which view rendered them.
 */
class SessionAndCredentialHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // The "array" cache store (see phpunit.xml) lives for the whole test
        // process and backs the rate limiter, so throttle assertions would
        // otherwise pass or fail depending on test ordering.
        Cache::flush();

        $this->user = User::create(['nickname' => 'member', 'password' => 'password123']);
    }

    public function test_the_session_id_changes_on_login(): void
    {
        $this->get(route('login'));

        $before = session()->getId();

        $this->post(route('login.attempt'), [
            'nickname' => 'member',
            'password' => 'password123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($this->user);
        $this->assertNotSame($before, session()->getId(), 'The pre-login session id survived authentication.');
    }

    public function test_the_session_id_and_csrf_token_change_on_logout(): void
    {
        $this->actingAs($this->user);
        $this->get(route('home'));

        $sessionBefore = session()->getId();
        $tokenBefore = session()->token();

        $this->post(route('logout'))->assertRedirect();

        $this->assertGuest();
        $this->assertNotSame($sessionBefore, session()->getId());
        $this->assertNotSame($tokenBefore, session()->token());
    }

    public function test_login_attempts_are_throttled(): void
    {
        $attempts = 0;

        do {
            $response = $this->post(route('login.attempt'), [
                'nickname' => 'member',
                'password' => 'wrong-password',
            ]);
            $attempts++;
        } while ($response->getStatusCode() !== 429 && $attempts < 20);

        $this->assertSame(429, $response->getStatusCode(), 'Login never started rejecting guesses.');
        $this->assertLessThanOrEqual(6, $attempts);
        $this->assertGuest();
    }

    /**
     * The limiter is keyed on the request rather than the submitted nickname,
     * so an attacker can't use "how many tries did I get" as a signal for
     * whether an account exists.
     */
    public function test_the_login_throttle_does_not_reveal_whether_an_account_exists(): void
    {
        $realAccountAttempts = $this->attemptsUntilThrottled('member');

        Cache::flush();

        $missingAccountAttempts = $this->attemptsUntilThrottled('does-not-exist');

        $this->assertSame($realAccountAttempts, $missingAccountAttempts);
    }

    private function attemptsUntilThrottled(string $nickname): int
    {
        $attempts = 0;

        do {
            $response = $this->post(route('login.attempt'), [
                'nickname' => $nickname,
                'password' => 'wrong-password',
            ]);
            $attempts++;
        } while ($response->getStatusCode() !== 429 && $attempts < 20);

        return $attempts;
    }

    /**
     * Three different failure causes, one indistinguishable outcome.
     * AuthPasswordTest covers the Discord-only-vs-wrong-password pair; the
     * nonexistent-account arm added here is the one an enumeration script
     * actually iterates over.
     */
    public function test_the_login_failure_message_is_identical_for_every_failure_mode(): void
    {
        $discordOnly = User::create(['nickname' => 'discordonly', 'password' => 'password123']);
        $discordOnly->forceFill(['password' => ''])->save();

        $messages = [];

        foreach ([
            'nonexistent' => ['nickname' => 'nobody-here', 'password' => 'password123'],
            'discord only' => ['nickname' => 'discordonly', 'password' => 'password123'],
            'wrong password' => ['nickname' => 'member', 'password' => 'not-the-password'],
        ] as $label => $credentials) {
            Cache::flush();

            $messages[$label] = $this->post(route('login.attempt'), $credentials)->getSession()->get('error');
        }

        $this->assertCount(1, array_unique($messages), 'Login failures are distinguishable: '.json_encode($messages));
        $this->assertNotEmpty(reset($messages));
    }

    /**
     * A single sweep covering User::$hidden, Combo::$hidden, ListModel::$hidden
     * and Game::$hidden = ['globalPass', 'modPass'] across both HTML and JSON
     * responses. The game passwords matter most: they are legacy columns that
     * no longer gate anything, but they are still stored, and a serialization
     * change that dropped $hidden would put them on a public page.
     */
    public function test_no_response_body_ever_contains_a_stored_password(): void
    {
        $game = Game::create([
            'name' => 'Test Game',
            'complete' => 1,
            'modPass' => 'MODERATOR-SECRET',
            'globalPass' => 'GLOBAL-SECRET',
        ]);

        $character = Character::create(['name' => 'Fighter', 'game_idgame' => $game->idgame]);
        $listingType = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 1]);

        $combo = Combo::create([
            'combo' => '5LP > 5MP',
            'character_idcharacter' => $character->idcharacter,
            'type' => $listingType->entryid,
            'user_iduser' => $this->user->iduser,
            'verified' => true,
            'password' => 'COMBO-SECRET',
        ]);

        $list = ListModel::create([
            'list_name' => 'Guide',
            'game_idgame' => $game->idgame,
            'password' => 'LIST-SECRET',
            'type' => 1,
            'user_iduser' => $this->user->iduser,
        ]);

        $admin = User::create(['nickname' => 'admin', 'password' => 'password123', 'is_admin' => true]);
        $hash = $admin->fresh()->password;

        $this->actingAs($admin);

        $urls = [
            route('admin.users.index'),
            route('users.show', $this->user),
            route('users.search', ['q' => 'mem']),
            route('lists.search', ['list_name' => 'Guide']),
            route('lists.show', $list),
            route('combos.show', $combo),
            route('games.show', $game),
            route('games.combos.index', $game),
        ];

        foreach ($urls as $url) {
            $body = $this->get($url)->assertOk()->getContent();

            foreach ([
                'a password hash' => $hash,
                'the game modPass' => 'MODERATOR-SECRET',
                'the game globalPass' => 'GLOBAL-SECRET',
                'the combo password' => 'COMBO-SECRET',
                'the list password' => 'LIST-SECRET',
            ] as $label => $secret) {
                $this->assertStringNotContainsString($secret, $body, "{$url} leaked {$label}");
            }
        }
    }

    public function test_the_user_search_endpoint_requires_authentication(): void
    {
        $this->get(route('users.search', ['q' => 'mem']))->assertRedirect(route('login'));
    }

    /**
     * The nickname typeahead returns whole User models. It selects only
     * iduser and nickname today; if that select list were ever dropped, every
     * other column would be serialized straight into JSON.
     */
    public function test_the_user_search_endpoint_returns_only_public_columns(): void
    {
        $this->actingAs($this->user);

        $results = $this->getJson(route('users.search', ['q' => 'mem']))->assertOk()->json();

        $this->assertNotEmpty($results);

        foreach ($results as $row) {
            $this->assertSame(['iduser', 'nickname'], array_keys($row));
        }
    }
}
