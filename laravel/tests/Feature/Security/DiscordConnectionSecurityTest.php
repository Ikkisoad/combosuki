<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\UserConnectedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Adversarial tests for Discord account linking.
 *
 * The threat that matters most here is a *forced link*: an attacker who can
 * get their own Discord account attached to a victim's Combo好き account owns
 * a second credential for an account with no email and no recovery path. Most
 * of what follows is aimed at that.
 *
 * Note which tests mock Socialite and which don't — the state tests
 * deliberately use the real provider, because mocking `user()` would mock away
 * the very check being tested.
 */
class DiscordConnectionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $victim;

    private User $attacker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->victim = User::create(['nickname' => 'victim', 'password' => 'victim-password']);
        $this->attacker = User::create(['nickname' => 'attacker', 'password' => 'attacker-password']);

        config([
            'services.discord.client_id' => 'client-id-123',
            'services.discord.client_secret' => 'client-secret-123',
            'services.discord.redirect' => 'https://combosuki.test/account/connections/discord/callback',
        ]);
    }

    private function discordUser(array $raw = []): SocialiteUser
    {
        $raw = array_merge([
            'id' => '123456789012345678',
            'username' => 'attackerdiscord',
            'discriminator' => '0',
            'email' => 'attacker@example.com',
            'verified' => true,
        ], $raw);

        $user = new SocialiteUser;
        $user->setRaw($raw)->map([
            'id' => $raw['id'],
            'nickname' => $raw['username'],
            'name' => $raw['username'],
            'email' => $raw['email'] ?? null,
        ]);
        $user->token = 'discord-access-token';

        return $user;
    }

    private function mockSocialiteUser(SocialiteUser $user): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('discord')->andReturn($provider);
    }

    private function intent(?User $user = null, ?int $expiresAt = null): array
    {
        return ['discord_link_intent' => [
            'user_iduser' => ($user ?? $this->victim)->iduser,
            'expires_at' => $expiresAt ?? now()->addMinutes(5)->timestamp,
        ]];
    }

    // ---------------------------------------------------------------- state

    /**
     * THE forced-link test. Socialite is NOT mocked: an attacker who obtained
     * a valid `code` from their own Discord authorization and lures a
     * logged-in victim to the callback must be stopped by the state check
     * before any token exchange happens. If this ever regresses, the attacker
     * gets their Discord attached to the victim's account.
     */
    public function test_callback_with_an_attacker_supplied_code_and_forged_state_is_rejected(): void
    {
        $this->actingAs($this->victim)
            ->withSession($this->intent())
            ->get(route('connections.discord.callback', [
                'code' => 'attacker-authorization-code',
                'state' => 'attacker-chosen-state',
            ]))
            ->assertRedirect(route('connections.edit'))
            // Asserting the *specific* message matters: the intent marker was
            // valid, so a pass here must come from Socialite's state check,
            // not from the earlier intent guard short-circuiting.
            ->assertSessionHas('error', "Couldn't complete the Discord connection. Please try again.");

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    /** Same attack, but the victim's session holds a real state the attacker can't guess. */
    public function test_callback_state_must_match_the_session_state(): void
    {
        $this->actingAs($this->victim)
            ->withSession(array_merge($this->intent(), ['state' => 'the-real-session-state']))
            ->get(route('connections.discord.callback', [
                'code' => 'attacker-authorization-code',
                'state' => 'not-the-session-state',
            ]))
            ->assertSessionHas('error', "Couldn't complete the Discord connection. Please try again.");

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    public function test_callback_with_no_code_at_all_is_rejected(): void
    {
        $this->actingAs($this->victim)
            ->withSession($this->intent())
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error', "Couldn't complete the Discord connection. Please try again.");

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    // --------------------------------------------------------------- intent

    /** The marker is pulled, not read — a replayed callback must not link twice. */
    public function test_intent_marker_is_single_use(): void
    {
        $this->mockSocialiteUser($this->discordUser());

        $session = $this->intent();

        $this->actingAs($this->victim)->withSession($session)
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('status');

        // Replay the exact same callback; the marker is gone from the session.
        $this->actingAs($this->victim)
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 1);
    }

    /**
     * A marker written by one user must not be usable by another, even though
     * Laravel's login only regenerates the session id and keeps session data.
     */
    public function test_a_leftover_marker_cannot_be_consumed_by_a_different_user(): void
    {
        $this->mockSocialiteUser($this->discordUser());

        $this->actingAs($this->attacker)
            ->withSession($this->intent($this->victim))
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    // ------------------------------------------------------- input handling

    /** Nothing from the request body may reach the created row. */
    public function test_extra_post_fields_cannot_override_the_owner_or_provider(): void
    {
        $this->mockSocialiteUser($this->discordUser());

        $this->actingAs($this->attacker)
            ->withSession($this->intent($this->attacker))
            ->get(route('connections.discord.callback', [
                'user_iduser' => $this->victim->iduser,
                'provider' => 'evil',
                'provider_user_id' => '000000000000000000',
            ]));

        $row = UserConnectedAccount::first();

        $this->assertNotNull($row);
        $this->assertSame('discord', $row->provider);
        $this->assertSame('123456789012345678', $row->provider_user_id);
        $this->assertSame($this->attacker->iduser, $row->user_iduser);
    }

    /**
     * `verified` is compared with === true. Discord sending a truthy-but-not-
     * true value (or an attacker-influenced proxy doing so) must not pass.
     *
     * @dataProvider truthyNonBooleans
     */
    #[DataProvider('truthyNonBooleans')]
    public function test_verified_gate_rejects_truthy_non_booleans(mixed $value): void
    {
        $this->mockSocialiteUser($this->discordUser(['verified' => $value]));

        $this->actingAs($this->victim)
            ->withSession($this->intent())
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    public static function truthyNonBooleans(): array
    {
        return [
            'string true' => ['true'],
            'string one' => ['1'],
            'int one' => [1],
            'string yes' => ['yes'],
            'array' => [['verified']],
        ];
    }

    /**
     * The snowflake is the value that lands in a unique index, so anything
     * that isn't plainly a run of ASCII digits must be refused.
     *
     * @dataProvider malformedSnowflakes
     */
    #[DataProvider('malformedSnowflakes')]
    public function test_malformed_snowflakes_are_refused(string $id): void
    {
        $this->mockSocialiteUser($this->discordUser(['id' => $id]));

        $this->actingAs($this->victim)
            ->withSession($this->intent())
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    public static function malformedSnowflakes(): array
    {
        return [
            'empty' => [''],
            'sql-ish' => ["1' OR '1'='1"],
            'negative' => ['-123456789012345'],
            'trailing newline' => ["123456789012345678\n"],
            'leading space' => [' 123456789012345678'],
            'arabic-indic digits' => ['١٢٣٤٥٦٧٨٩٠١٢٣٤٥٦٧٨'],
            'too long' => [str_repeat('9', 21)],
            'null byte' => ["123456789012345678\0"],
        ];
    }

    /**
     * Regression for a real finding: an over-long nickname used to hit MySQL's
     * STRICT_TRANS_TABLES as a data-too-long error, get swallowed by an
     * over-broad QueryException catch, and be reported to the user as "already
     * connected to another Combo好き account" — wrong, and misleading about
     * whether that Discord id is taken. SQLite ignores column widths, so this
     * asserts the truncation itself, which holds on both engines.
     */
    public function test_an_over_long_nickname_is_truncated_rather_than_breaking_the_insert(): void
    {
        $this->mockSocialiteUser($this->discordUser(['username' => str_repeat('A', 300)]));

        $this->actingAs($this->victim)
            ->withSession($this->intent())
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('status');

        $this->assertSame(190, mb_strlen(UserConnectedAccount::first()->provider_nickname));
    }

    /** A hostile Discord display name must not execute in the page. */
    public function test_a_hostile_discord_nickname_is_escaped_in_the_page(): void
    {
        $payload = '<script>alert(1)</script>';

        $this->mockSocialiteUser($this->discordUser(['username' => $payload]));

        $this->actingAs($this->victim)
            ->withSession($this->intent())
            ->get(route('connections.discord.callback'));

        $this->actingAs($this->victim)
            ->get(route('connections.edit'))
            ->assertOk()
            ->assertDontSee($payload, false)
            ->assertSee('&lt;script&gt;', false);
    }

    // ------------------------------------------------------ authz and verbs

    public function test_one_user_cannot_unlink_another_users_connection(): void
    {
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '999999999999999999',
            'provider_nickname' => 'victimdiscord',
            'user_iduser' => $this->victim->iduser,
        ]);

        $this->actingAs($this->attacker)
            ->post(route('connections.discord.destroy'), ['current_password' => 'attacker-password'])
            ->assertRedirect(route('connections.edit'));

        $this->assertDatabaseHas('user_connected_account', [
            'user_iduser' => $this->victim->iduser,
            'provider_user_id' => '999999999999999999',
        ]);
    }

    /** Unlink must not be reachable by a link the attacker can get the victim to click. */
    public function test_unlink_is_not_reachable_by_get(): void
    {
        $this->actingAs($this->victim)
            ->get('/account/connections/discord/delete')
            ->assertStatus(405);
    }

    public function test_link_initiation_is_not_reachable_by_get(): void
    {
        $this->actingAs($this->victim)
            ->get('/account/connections/discord')
            ->assertStatus(405);
    }

    /**
     * A user with no usable password can't confirm anything, so they must not
     * be able to link by POSTing straight past the UI's blocked state.
     */
    public function test_a_passwordless_user_cannot_link_by_posting_directly(): void
    {
        DB::table('user')->where('iduser', $this->victim->iduser)->update(['password' => null]);

        $this->actingAs($this->victim->fresh())
            ->post(route('connections.discord.redirect'), ['current_password' => ''])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($this->victim->fresh())
            ->post(route('connections.discord.redirect'), ['current_password' => 'anything'])
            ->assertSessionHas('error');

        $this->assertNull(session('discord_link_intent'));
    }

    // ---------------------------------------------------------- rate limits

    /** The password-taking route is a password oracle; it must throttle. */
    public function test_password_guessing_on_the_link_route_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->victim)
                ->post(route('connections.discord.redirect'), ['current_password' => "guess-$i"])
                ->assertRedirect();
        }

        $this->actingAs($this->victim)
            ->post(route('connections.discord.redirect'), ['current_password' => 'guess-6'])
            ->assertStatus(429);
    }

    public function test_password_guessing_on_the_unlink_route_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->victim)
                ->post(route('connections.discord.destroy'), ['current_password' => "guess-$i"]);
        }

        $this->actingAs($this->victim)
            ->post(route('connections.discord.destroy'), ['current_password' => 'guess-6'])
            ->assertStatus(429);
    }

    // --------------------------------------------------------- data leakage

    /** The email is requested for the gate only and must not reach the logs. */
    public function test_the_discord_email_is_never_written_to_the_log(): void
    {
        $logFile = storage_path('logs/laravel.log');
        $before = file_exists($logFile) ? filesize($logFile) : 0;

        $this->mockSocialiteUser($this->discordUser(['verified' => false]));

        $this->actingAs($this->victim)
            ->withSession($this->intent())
            ->get(route('connections.discord.callback'));

        $written = file_exists($logFile)
            ? (string) file_get_contents($logFile, false, null, $before)
            : '';

        $this->assertStringNotContainsString('attacker@example.com', $written);
        $this->assertStringNotContainsString('discord-access-token', $written);
    }

    /** The refusal must not disclose which account holds a given Discord id. */
    public function test_the_refusal_message_does_not_disclose_the_other_account(): void
    {
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'victimdiscord',
            'user_iduser' => $this->victim->iduser,
        ]);

        $this->mockSocialiteUser($this->discordUser());

        $this->actingAs($this->attacker)
            ->withSession($this->intent($this->attacker))
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $error = session('error');

        $this->assertStringNotContainsString('victim', $error);
        $this->assertStringNotContainsString('victimdiscord', $error);
        $this->assertStringNotContainsString((string) $this->victim->iduser, $error);
    }
}
