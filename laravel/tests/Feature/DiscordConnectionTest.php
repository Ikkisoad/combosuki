<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserConnectedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class DiscordConnectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create(['nickname' => 'fan', 'password' => 'password123']);

        config([
            'services.discord.client_id' => 'client-id-123',
            'services.discord.client_secret' => 'client-secret-123',
            'services.discord.redirect' => 'https://combosuki.test/account/connections/discord/callback',
        ]);
    }

    /**
     * Builds what Socialite hands back from Discord. `verified` lives on the
     * raw payload (it only appears at all when the `email` scope was granted),
     * which is exactly where the controller reads it from.
     */
    private function discordUser(array $raw = []): SocialiteUser
    {
        $raw = array_merge([
            'id' => '123456789012345678',
            'username' => 'fanuser',
            'discriminator' => '0',
            'email' => 'fan@example.com',
            'verified' => true,
        ], $raw);

        $socialiteUser = new SocialiteUser;
        $socialiteUser->setRaw($raw)->map([
            'id' => $raw['id'],
            'nickname' => $raw['username'],
            'name' => $raw['username'],
            'email' => $raw['email'] ?? null,
        ]);
        $socialiteUser->token = 'discord-access-token';

        return $socialiteUser;
    }

    private function mockSocialiteUser(SocialiteUser $user): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('discord')->andReturn($provider);
    }

    /** Puts the session into the state redirectToDiscord() leaves behind. */
    private function withLinkIntent(?User $user = null, ?int $expiresAt = null): array
    {
        return ['discord_link_intent' => [
            'user_iduser' => ($user ?? $this->user)->iduser,
            'expires_at' => $expiresAt ?? now()->addMinutes(5)->timestamp,
        ]];
    }

    public function test_guests_cannot_reach_any_connection_route(): void
    {
        $this->get(route('connections.edit'))->assertRedirect(route('login'));
        $this->post(route('connections.discord.redirect'))->assertRedirect(route('login'));
        $this->post(route('connections.discord.destroy'))->assertRedirect(route('login'));
    }

    public function test_page_shows_the_not_connected_state(): void
    {
        $this->actingAs($this->user)
            ->get(route('connections.edit'))
            ->assertOk()
            ->assertSee('No Discord account connected.');
    }

    public function test_linking_requires_a_password(): void
    {
        $this->actingAs($this->user)
            ->post(route('connections.discord.redirect'))
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    public function test_linking_rejects_a_wrong_password(): void
    {
        $this->actingAs($this->user)
            ->post(route('connections.discord.redirect'), ['current_password' => 'nope'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull(session('discord_link_intent'));
    }

    /**
     * Some legacy rows hold a value that isn't a valid bcrypt hash. The
     * `current_password` rule would 500 on those; the ConfirmsPassword guard
     * turns it into an ordinary failed check.
     */
    public function test_a_user_with_a_non_bcrypt_hash_gets_an_error_not_a_server_error(): void
    {
        DB::table('user')->where('iduser', $this->user->iduser)->update(['password' => 'plaintext-legacy']);

        $this->actingAs($this->user->fresh())
            ->post(route('connections.discord.redirect'), ['current_password' => 'plaintext-legacy'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    public function test_a_user_without_a_password_is_told_to_set_one(): void
    {
        DB::table('user')->where('iduser', $this->user->iduser)->update(['password' => null]);

        $this->actingAs($this->user->fresh())
            ->get(route('connections.edit'))
            ->assertOk()
            ->assertSee("there's no way to confirm", false);
    }

    public function test_correct_password_redirects_to_discord_with_the_expected_query(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('connections.discord.redirect'), ['current_password' => 'password123']);

        $location = $response->headers->get('Location');

        $this->assertStringContainsString('discord.com/api/oauth2/authorize', $location);

        $query = [];
        parse_str(parse_url($location, PHP_URL_QUERY) ?? '', $query);

        $this->assertSame('code', $query['response_type']);
        $this->assertSame('client-id-123', $query['client_id']);
        $this->assertSame('identify email', $query['scope']);
        $this->assertNotEmpty($query['state']);
        // The provider forces prompt=none unless withConsent() is called,
        // which would silently authorize whatever Discord session is live.
        $this->assertSame('consent', $query['prompt']);

        $this->assertSame($this->user->iduser, session('discord_link_intent')['user_iduser']);
    }

    public function test_callback_links_a_verified_discord_account(): void
    {
        $this->mockSocialiteUser($this->discordUser());

        $this->actingAs($this->user)
            ->withSession($this->withLinkIntent())
            ->get(route('connections.discord.callback'))
            ->assertRedirect(route('connections.edit'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('user_connected_account', [
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'fanuser',
            'user_iduser' => $this->user->iduser,
        ]);
    }

    public function test_neither_the_token_nor_the_email_is_persisted(): void
    {
        $this->mockSocialiteUser($this->discordUser());

        $this->actingAs($this->user)
            ->withSession($this->withLinkIntent())
            ->get(route('connections.discord.callback'));

        $row = (array) DB::table('user_connected_account')->first();

        $this->assertNotEmpty($row);
        foreach ($row as $column => $value) {
            $this->assertStringNotContainsString('token', (string) $column);
            $this->assertStringNotContainsString('email', (string) $column);
            $this->assertNotSame('discord-access-token', $value);
            $this->assertNotSame('fan@example.com', $value);
        }
    }

    public function test_callback_rejects_an_unverified_discord_account(): void
    {
        $this->mockSocialiteUser($this->discordUser(['verified' => false]));

        $this->actingAs($this->user)
            ->withSession($this->withLinkIntent())
            ->get(route('connections.discord.callback'))
            ->assertRedirect(route('connections.edit'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    /** Fail closed: a payload with no `verified` key must not be treated as verified. */
    public function test_callback_rejects_a_payload_with_no_verified_flag(): void
    {
        $raw = ['id' => '123456789012345678', 'username' => 'fanuser', 'discriminator' => '0'];

        $socialiteUser = new SocialiteUser;
        $socialiteUser->setRaw($raw)->map(['id' => $raw['id'], 'nickname' => 'fanuser', 'name' => 'fanuser']);
        $this->mockSocialiteUser($socialiteUser);

        $this->actingAs($this->user)
            ->withSession($this->withLinkIntent())
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    public function test_a_discord_account_claimed_by_someone_else_cannot_be_linked(): void
    {
        // Nickname deliberately not a substring of the refusal message
        // ("another" would trivially satisfy a search for "other").
        $other = User::create(['nickname' => 'rival', 'password' => 'password123']);
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'someoneelse',
            'user_iduser' => $other->iduser,
        ]);

        $this->mockSocialiteUser($this->discordUser());

        $this->actingAs($this->user)
            ->withSession($this->withLinkIntent())
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 1);
        // The existing link is untouched, and the message never names its owner.
        $this->assertSame($other->iduser, UserConnectedAccount::first()->user_iduser);
        $this->assertStringNotContainsString('rival', session('error'));
        $this->assertStringNotContainsString('someoneelse', session('error'));
    }

    public function test_a_user_who_is_already_linked_must_disconnect_first(): void
    {
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '999999999999999999',
            'provider_nickname' => 'existing',
            'user_iduser' => $this->user->iduser,
        ]);

        $this->mockSocialiteUser($this->discordUser());

        $this->actingAs($this->user)
            ->withSession($this->withLinkIntent())
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 1);
        $this->assertDatabaseHas('user_connected_account', ['provider_user_id' => '999999999999999999']);
    }

    public function test_callback_without_an_intent_marker_is_rejected(): void
    {
        $this->mockSocialiteUser($this->discordUser());

        $this->actingAs($this->user)
            ->get(route('connections.discord.callback'))
            ->assertRedirect(route('connections.edit'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    public function test_callback_with_an_expired_intent_marker_is_rejected(): void
    {
        $this->mockSocialiteUser($this->discordUser());

        $this->actingAs($this->user)
            ->withSession($this->withLinkIntent(expiresAt: now()->subMinute()->timestamp))
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    /** A marker confirmed by a different user must not complete for this one. */
    public function test_callback_with_another_users_intent_marker_is_rejected(): void
    {
        $other = User::create(['nickname' => 'other', 'password' => 'password123']);

        $this->mockSocialiteUser($this->discordUser());

        $this->actingAs($this->user)
            ->withSession($this->withLinkIntent($other))
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    public function test_callback_rejects_a_malformed_snowflake(): void
    {
        $this->mockSocialiteUser($this->discordUser(['id' => 'not-a-snowflake']));

        $this->actingAs($this->user)
            ->withSession($this->withLinkIntent())
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    public function test_unlinking_requires_the_correct_password(): void
    {
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'fanuser',
            'user_iduser' => $this->user->iduser,
        ]);

        $this->actingAs($this->user)
            ->post(route('connections.discord.destroy'), ['current_password' => 'wrong'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 1);

        $this->actingAs($this->user)
            ->post(route('connections.discord.destroy'), ['current_password' => 'password123'])
            ->assertRedirect(route('connections.edit'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    public function test_unlinking_only_removes_your_own_connection(): void
    {
        $other = User::create(['nickname' => 'other', 'password' => 'password123']);
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '999999999999999999',
            'provider_nickname' => 'someoneelse',
            'user_iduser' => $other->iduser,
        ]);

        $this->actingAs($this->user)
            ->post(route('connections.discord.destroy'), ['current_password' => 'password123']);

        $this->assertDatabaseHas('user_connected_account', ['user_iduser' => $other->iduser]);
    }
}
