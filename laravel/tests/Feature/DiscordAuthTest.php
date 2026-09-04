<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Models\UserConnectedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DiscordAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.client_id' => 'client-id-123',
            'services.discord.client_secret' => 'client-secret-123',
            'services.discord.redirect' => 'https://combosuki.test/account/connections/discord/callback',
            'services.discord.auth_redirect' => 'https://combosuki.test/auth/discord/callback',
        ]);
    }

    private function discordUser(array $raw = []): SocialiteUser
    {
        $raw = array_merge([
            'id' => '123456789012345678',
            'username' => 'newcomer',
            'discriminator' => '0',
            'email' => 'newcomer@example.com',
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

    /** The auth controller chains ->redirectUrl(...) before ->user(). */
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

    private function identity(array $overrides = [], ?int $expiresAt = null): array
    {
        return ['discord_registration_identity' => array_merge([
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'newcomer',
            'expires_at' => $expiresAt ?? now()->addMinutes(15)->timestamp,
        ], $overrides)];
    }

    // ------------------------------------------------------------- redirect

    public function test_login_page_offers_discord_when_the_integration_is_on(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Continue with Discord');
    }

    public function test_redirect_sends_the_user_to_discord_with_the_expected_query(): void
    {
        $response = $this->post(route('auth.discord.redirect'));

        $location = $response->headers->get('Location');
        $this->assertStringContainsString('discord.com/api/oauth2/authorize', $location);

        $query = [];
        parse_str(parse_url($location, PHP_URL_QUERY) ?? '', $query);

        $this->assertSame('code', $query['response_type']);
        $this->assertSame('identify email', $query['scope']);
        $this->assertNotEmpty($query['state']);
        // Without withConsent() the provider forces prompt=none, which for a
        // sign-in button would log someone into an account they never chose.
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame('https://combosuki.test/auth/discord/callback', $query['redirect_uri']);
    }

    // ---------------------------------------------------------------- login

    public function test_a_linked_discord_account_signs_the_user_in(): void
    {
        $user = User::create(['nickname' => 'veteran', 'password' => 'password123']);
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'newcomer',
            'user_iduser' => $user->iduser,
        ]);

        $this->mockSocialiteUser($this->discordUser());

        $this->withSession($this->authIntent())
            ->get(route('auth.discord.callback'))
            ->assertRedirect(route('home'));

        $this->assertTrue(Auth::check());
        $this->assertSame($user->iduser, Auth::user()->iduser);
        // No new account was invented for an already-linked identity.
        $this->assertSame(1, User::count());
    }

    /** See AuthPasswordTest::test_password_login_sets_a_remember_cookie. */
    public function test_signing_in_with_discord_sets_a_remember_cookie(): void
    {
        $user = User::create(['nickname' => 'veteran', 'password' => 'password123']);
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'newcomer',
            'user_iduser' => $user->iduser,
        ]);

        $this->mockSocialiteUser($this->discordUser());

        $response = $this->withSession($this->authIntent())->get(route('auth.discord.callback'));

        $names = array_map(fn ($c) => $c->getName(), $response->headers->getCookies());
        $this->assertContains(Auth::guard()->getRecallerName(), $names);
    }

    public function test_an_unverified_discord_email_cannot_sign_in(): void
    {
        $user = User::create(['nickname' => 'veteran', 'password' => 'password123']);
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'newcomer',
            'user_iduser' => $user->iduser,
        ]);

        $this->mockSocialiteUser($this->discordUser(['verified' => false]));

        $this->withSession($this->authIntent())
            ->get(route('auth.discord.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertFalse(Auth::check());
    }

    // --------------------------------------------------------- registration

    public function test_an_unknown_discord_account_is_sent_to_pick_a_nickname_without_creating_anything(): void
    {
        $this->mockSocialiteUser($this->discordUser());

        $this->withSession($this->authIntent())
            ->get(route('auth.discord.callback'))
            ->assertRedirect(route('auth.discord.register'));

        $this->assertFalse(Auth::check());
        $this->assertSame(0, User::count());
        $this->assertDatabaseCount('user_connected_account', 0);
    }

    public function test_the_nickname_form_prefills_from_the_discord_username(): void
    {
        $this->withSession($this->identity())
            ->get(route('auth.discord.register'))
            ->assertOk()
            ->assertSee('value="newcomer"', false);
    }

    /** A username made only of characters the policy rejects leaves the field empty. */
    public function test_an_unusable_discord_username_prefills_nothing(): void
    {
        $this->withSession($this->identity(['provider_nickname' => '★★★']))
            ->get(route('auth.discord.register'))
            ->assertOk()
            ->assertSee('name="nickname"', false)
            ->assertDontSee('value="★★★"', false);
    }

    public function test_registering_creates_the_account_and_signs_the_user_in(): void
    {
        $this->withSession($this->identity())
            ->post(route('auth.discord.register.store'), ['nickname' => 'newcomer'])
            ->assertRedirect(route('home'));

        $user = User::where('nickname', 'newcomer')->firstOrFail();

        $this->assertNull($user->password);
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->trusted_user);
        $this->assertFalse($user->is_moderator);

        $this->assertDatabaseHas('user_connected_account', [
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'user_iduser' => $user->iduser,
        ]);

        $this->assertTrue(Auth::check());
        $this->assertSame($user->iduser, Auth::user()->iduser);
    }

    public function test_registration_ignores_privilege_fields_in_the_request(): void
    {
        $this->withSession($this->identity())
            ->post(route('auth.discord.register.store'), [
                'nickname' => 'newcomer',
                'is_admin' => 1,
                'trusted_user' => 1,
                'is_moderator' => 1,
                'password' => 'injected-password',
            ]);

        $user = User::where('nickname', 'newcomer')->firstOrFail();

        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->trusted_user);
        $this->assertFalse($user->is_moderator);
        $this->assertNull($user->password);
    }

    public function test_registration_without_a_discord_identity_creates_nothing(): void
    {
        $this->post(route('auth.discord.register.store'), ['nickname' => 'newcomer'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertSame(0, User::count());
    }

    public function test_registration_with_an_expired_identity_creates_nothing(): void
    {
        $this->withSession($this->identity(expiresAt: now()->subMinute()->timestamp))
            ->post(route('auth.discord.register.store'), ['nickname' => 'newcomer'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertSame(0, User::count());
    }

    /**
     * The Discord id is unique across accounts, so a second registration with
     * the same identity must be refused by the linker, and the transaction
     * must take the half-created user back out with it.
     */
    public function test_a_discord_account_already_linked_cannot_register_again(): void
    {
        $existing = User::create(['nickname' => 'veteran', 'password' => 'password123']);
        UserConnectedAccount::create([
            'provider' => 'discord',
            'provider_user_id' => '123456789012345678',
            'provider_nickname' => 'newcomer',
            'user_iduser' => $existing->iduser,
        ]);

        $this->withSession($this->identity())
            ->post(route('auth.discord.register.store'), ['nickname' => 'brandnew'])
            ->assertSessionHas('error');

        $this->assertNull(User::where('nickname', 'brandnew')->first());
        $this->assertSame(1, User::count());
        $this->assertDatabaseCount('user_connected_account', 1);
    }

    // ------------------------------------------------------------- nickname

    /**
     * @dataProvider rejectedNicknames
     */
    #[DataProvider('rejectedNicknames')]
    public function test_rejected_nicknames(string $nickname): void
    {
        $this->withSession($this->identity())
            ->post(route('auth.discord.register.store'), ['nickname' => $nickname])
            ->assertSessionHasErrors('nickname');

        $this->assertSame(0, User::count());
    }

    public static function rejectedNicknames(): array
    {
        return [
            'too short' => ['ab'],
            'too long' => [str_repeat('a', 46)],
            'reserved' => ['admin'],
            'reserved mixed case' => ['AdMiN'],
            'reserved support' => ['support'],
            'space' => ['new comer'],
            'html' => ['<script>'],
            'cyrillic homoglyph' => ['аdmin'],
            'emoji' => ['newcomer🎮'],
        ];
    }

    /**
     * MySQL's _ci collation already treats these as the same name; SQLite does
     * not. NicknamePolicy compares lowercased values so both agree — see the
     * typing note in CLAUDE.md.
     */
    public function test_a_nickname_differing_only_in_case_is_taken(): void
    {
        User::create(['nickname' => 'Veteran', 'password' => 'password123']);

        $this->withSession($this->identity())
            ->post(route('auth.discord.register.store'), ['nickname' => 'veteran'])
            ->assertSessionHasErrors('nickname');

        $this->assertSame(1, User::count());
    }

    // ----------------------------------------------------------------- flag

    public function test_every_discord_auth_route_is_404_when_the_integration_is_off(): void
    {
        SiteSetting::current()->update(['discord_integration_enabled' => false]);
        SiteSetting::forgetCurrent();

        $this->post(route('auth.discord.redirect'))->assertNotFound();
        $this->get(route('auth.discord.callback'))->assertNotFound();
        $this->get(route('auth.discord.register'))->assertNotFound();
        $this->post(route('auth.discord.register.store'), ['nickname' => 'newcomer'])->assertNotFound();

        $this->assertSame(0, User::count());
    }

    public function test_the_login_page_hides_discord_when_the_integration_is_off(): void
    {
        SiteSetting::current()->update(['discord_integration_enabled' => false]);
        SiteSetting::forgetCurrent();

        $this->get(route('login'))->assertOk()->assertDontSee('Continue with Discord');
    }
}
