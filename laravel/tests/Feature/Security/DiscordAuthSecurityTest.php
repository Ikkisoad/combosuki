<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\UserConnectedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Adversarial tests for signing in and signing up with Discord.
 *
 * Registration is the first guest-reachable endpoint in this app that creates
 * rows, and a Discord-only account has exactly one credential in a system with
 * no email and no password reset. The two threats that matter: someone signing
 * a victim into an account the victim didn't choose, and someone creating
 * accounts without ever passing through Discord.
 */
class DiscordAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.client_id' => 'client-id-123',
            'services.discord.client_secret' => 'client-secret-123',
            'services.discord.auth_redirect' => 'https://combosuki.test/auth/discord/callback',
        ]);
    }

    private function discordUser(array $raw = []): SocialiteUser
    {
        $raw = array_merge([
            'id' => '123456789012345678',
            'username' => 'attacker',
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
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('setHttpClient')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('discord')->andReturn($provider);
    }

    private function authIntent(?int $expiresAt = null): array
    {
        return ['discord_auth_intent' => ['expires_at' => $expiresAt ?? now()->addMinutes(5)->timestamp]];
    }

    private function identity(?int $expiresAt = null): array
    {
        return ['discord_registration_identity' => [
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'attacker',
            'expires_at' => $expiresAt ?? now()->addMinutes(15)->timestamp,
        ]];
    }

    // ---------------------------------------------------------------- state

    /**
     * Socialite deliberately NOT mocked. An attacker holding a `code` from
     * their own Discord authorization must not be able to complete someone
     * else's callback — otherwise they could sign a victim's browser into the
     * attacker's account (or worse, register one silently).
     */
    public function test_a_forged_state_cannot_complete_the_sign_in(): void
    {
        $this->withSession($this->authIntent())
            ->get(route('auth.discord.callback', [
                'code' => 'attacker-authorization-code',
                'state' => 'attacker-chosen-state',
            ]))
            ->assertRedirect(route('login'))
            // The specific message proves it died at Socialite's state check,
            // not earlier at the intent guard.
            ->assertSessionHas('error', "Couldn't complete the Discord sign-in. Please try again.");

        $this->assertFalse(Auth::check());
        $this->assertSame(0, User::count());
    }

    public function test_state_must_match_the_session_state(): void
    {
        $this->withSession(array_merge($this->authIntent(), ['state' => 'the-real-state']))
            ->get(route('auth.discord.callback', ['code' => 'c', 'state' => 'not-the-real-state']))
            ->assertSessionHas('error', "Couldn't complete the Discord sign-in. Please try again.");

        $this->assertFalse(Auth::check());
    }

    // --------------------------------------------------------------- intent

    public function test_the_callback_without_an_intent_marker_is_rejected(): void
    {
        $this->mockSocialiteUser($this->discordUser());

        $this->get(route('auth.discord.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertSame(0, User::count());
    }

    public function test_the_callback_with_an_expired_intent_marker_is_rejected(): void
    {
        $this->mockSocialiteUser($this->discordUser());

        $this->withSession($this->authIntent(now()->subMinute()->timestamp))
            ->get(route('auth.discord.callback'))
            ->assertSessionHas('error');

        $this->assertSame(0, User::count());
    }

    /**
     * The link flow (phase 1) and the auth flow use separate session keys on
     * separate endpoints. Neither marker may satisfy the other.
     */
    public function test_a_link_marker_cannot_satisfy_the_auth_callback(): void
    {
        $this->mockSocialiteUser($this->discordUser());

        $this->withSession(['discord_link_intent' => [
            'user_iduser' => 1,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]])
            ->get(route('auth.discord.callback'))
            ->assertSessionHas('error');

        $this->assertSame(0, User::count());
    }

    public function test_an_auth_marker_cannot_satisfy_the_link_callback(): void
    {
        $user = User::create(['nickname' => 'victim', 'password' => 'password123']);

        $this->mockSocialiteUser($this->discordUser());

        $this->actingAs($user)
            ->withSession($this->authIntent())
            ->get(route('connections.discord.callback'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_connected_account', 0);
    }

    // --------------------------------------------------------- registration

    /** The nickname form must be worthless without a completed OAuth round-trip. */
    public function test_posting_the_registration_form_without_oauth_creates_nothing(): void
    {
        $this->post(route('auth.discord.register.store'), [
            'nickname' => 'freeaccount',
            'provider_user_id' => '999999999999999999',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, User::count());
        $this->assertDatabaseCount('user_connected_account', 0);
    }

    /** The Discord id comes from the session, never from the request body. */
    public function test_the_request_cannot_choose_which_discord_id_gets_linked(): void
    {
        $this->withSession($this->identity())
            ->post(route('auth.discord.register.store'), [
                'nickname' => 'newcomer',
                'provider_user_id' => '999999999999999999',
                'provider' => 'evil',
            ]);

        $row = UserConnectedAccount::firstOrFail();

        $this->assertSame('discord', $row->provider);
        $this->assertSame('123456789012345678', $row->provider_user_id);
    }

    public function test_the_identity_is_consumed_so_a_replay_cannot_create_a_second_account(): void
    {
        $this->withSession($this->identity())
            ->post(route('auth.discord.register.store'), ['nickname' => 'firstone']);

        $this->assertSame(1, User::count());

        // Replay the same POST. Two things now stop it: the identity is gone
        // from the session, and the first registration signed us in, so the
        // guest-only middleware turns it away before the controller runs.
        $this->post(route('auth.discord.register.store'), ['nickname' => 'secondone'])
            ->assertRedirect();

        $this->assertSame(1, User::count());
        $this->assertNull(User::where('nickname', 'secondone')->first());
    }

    /** A nickname that impersonates staff must not reach the public profile page. */
    public function test_reserved_and_homoglyph_nicknames_cannot_be_registered(): void
    {
        // Five at most: this route carries throttle:5,1, and a 429 would make
        // the assertion below pass for the wrong reason.
        foreach (['admin', 'Administrator', 'moderator', 'combosuki', 'аdmin'] as $nickname) {
            $this->withSession($this->identity())
                ->post(route('auth.discord.register.store'), ['nickname' => $nickname])
                ->assertSessionHasErrors('nickname');
        }

        $this->assertSame(0, User::count());
    }

    // ----------------------------------------------------------- session/PII

    /** A pre-login session id must not survive becoming an authenticated one. */
    public function test_the_session_id_is_regenerated_on_sign_in(): void
    {
        $user = User::create(['nickname' => 'veteran', 'password' => 'password123']);
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'attacker',
            'user_iduser' => $user->iduser,
        ]);

        $this->mockSocialiteUser($this->discordUser());

        $this->withSession($this->authIntent());
        $before = session()->getId();

        $this->get(route('auth.discord.callback'));

        $this->assertNotSame($before, session()->getId());
    }

    public function test_the_discord_email_and_token_never_reach_the_log(): void
    {
        $logFile = storage_path('logs/laravel.log');
        $before = file_exists($logFile) ? filesize($logFile) : 0;

        $this->mockSocialiteUser($this->discordUser());

        $this->withSession($this->authIntent())->get(route('auth.discord.callback'));
        $this->withSession($this->identity())->post(route('auth.discord.register.store'), ['nickname' => 'newcomer']);

        $written = file_exists($logFile) ? (string) file_get_contents($logFile, false, null, $before) : '';

        $this->assertStringNotContainsString('attacker@example.com', $written);
        $this->assertStringNotContainsString('discord-access-token', $written);
    }

    // ---------------------------------------------------------- rate limits

    public function test_registration_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withSession($this->identity())
                ->post(route('auth.discord.register.store'), ['nickname' => 'ab']);
        }

        $this->withSession($this->identity())
            ->post(route('auth.discord.register.store'), ['nickname' => 'ab'])
            ->assertStatus(429);
    }

    public function test_starting_the_discord_sign_in_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('auth.discord.redirect'));
        }

        $this->post(route('auth.discord.redirect'))->assertStatus(429);
    }
}
