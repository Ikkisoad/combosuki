<?php

namespace Tests\Feature\Security;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Laravel's PreventRequestForgery middleware short-circuits whenever the app
 * is running unit tests (runningInConsole() && app['env'] === 'testing'),
 * which phpunit.xml sets for every test in this suite. That means none of the
 * other ~100 test classes prove anything at all about CSRF, and
 * $this->withMiddleware() doesn't help — the middleware runs, it just
 * disables itself.
 *
 * So this file lies about the environment for the duration of a single
 * request, which is the only way to make the real token check execute. It
 * also pins the two deliberate exemptions: routes/discord.php and
 * routes/activity.php are registered outside the `web` group on purpose, and
 * that is exactly the sort of thing someone "fixes" by moving them back in,
 * which would break Discord in production while every test stayed green.
 */
class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->user = User::create(['nickname' => 'member', 'password' => 'password123']);
    }

    /**
     * Call immediately before the request under test and nothing else: while
     * this is in effect the app no longer looks like a test run, so anything
     * that branches on the environment behaves differently.
     */
    private function enforceCsrf(): void
    {
        $this->app['env'] = 'production';
    }

    public function test_a_state_changing_post_without_a_token_is_rejected(): void
    {
        $this->actingAs($this->user);

        $this->enforceCsrf();

        $this->post(route('lists.store'), ['list_name' => 'Forged'])->assertStatus(419);

        $this->assertDatabaseCount('list', 0);
    }

    public function test_a_state_changing_post_with_a_wrong_token_is_rejected(): void
    {
        $this->actingAs($this->user)->withSession(['_token' => 'the-real-token']);

        $this->enforceCsrf();

        $this->post(route('lists.store'), [
            'list_name' => 'Forged',
            '_token' => 'a-guessed-token',
        ])->assertStatus(419);

        $this->assertDatabaseCount('list', 0);
    }

    /**
     * The positive control: without this, a change that rejected every
     * request regardless of token would pass the two tests above.
     */
    public function test_a_state_changing_post_with_the_matching_token_succeeds(): void
    {
        $this->actingAs($this->user)->withSession(['_token' => 'the-real-token']);

        $this->enforceCsrf();

        $this->post(route('lists.store'), [
            'list_name' => 'Legitimate',
            '_token' => 'the-real-token',
        ])->assertRedirect();

        $this->assertDatabaseHas('list', ['list_name' => 'Legitimate']);
    }

    /**
     * PreventRequestForgery also accepts a request whose Sec-Fetch-Site says
     * same-origin, and can be configured to accept same-site. bootstrap/app.php
     * never calls validateCsrfTokens(), so $allowSameSite stays false — which
     * matters specifically because this app is growing a comble.* subdomain,
     * and same-site would cover a request coming from it.
     */
    public function test_a_same_site_fetch_metadata_header_does_not_bypass_the_token_check(): void
    {
        $this->actingAs($this->user);

        $this->enforceCsrf();

        $this->withHeaders(['Sec-Fetch-Site' => 'same-site'])
            ->post(route('lists.store'), ['list_name' => 'Forged'])
            ->assertStatus(419);

        $this->assertDatabaseCount('list', 0);
    }

    public function test_a_cross_site_fetch_metadata_header_does_not_bypass_the_token_check(): void
    {
        $this->actingAs($this->user);

        $this->enforceCsrf();

        $this->withHeaders(['Sec-Fetch-Site' => 'cross-site'])
            ->post(route('lists.store'), ['list_name' => 'Forged'])
            ->assertStatus(419);

        $this->assertDatabaseCount('list', 0);
    }

    /**
     * Discord signs its interaction requests with Ed25519 and has no way to
     * carry a Laravel session token, so this endpoint must stay outside CSRF.
     * Guarded by VerifyDiscordSignature instead — see
     * DiscordSignatureSecurityTest.
     */
    public function test_the_discord_interactions_endpoint_is_deliberately_outside_csrf(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        config(['services.discord.public_key' => sodium_bin2hex(sodium_crypto_sign_publickey($keypair))]);
        Http::fake(['discord.com/*' => Http::response(['id' => 'test-message-id'], 200)]);

        $body = json_encode(['type' => 1]);
        $timestamp = (string) time();
        $signature = sodium_bin2hex(sodium_crypto_sign_detached(
            $timestamp.$body,
            sodium_crypto_sign_secretkey($keypair)
        ));

        $this->enforceCsrf();

        $this->call('POST', route('discord.interactions'), server: [
            'HTTP_X-Signature-Ed25519' => $signature,
            'HTTP_X-Signature-Timestamp' => $timestamp,
            'CONTENT_TYPE' => 'application/json',
        ], content: $body)->assertOk();
    }

    /**
     * The Activity runs inside Discord's iframe with no Laravel session, so
     * its token exchange can't carry a CSRF token either. A 422 here means
     * the request reached validation — i.e. it was not turned away as a
     * forgery.
     */
    public function test_the_activity_token_endpoint_is_deliberately_outside_csrf(): void
    {
        // Otherwise EnsureDiscordActivityEnabled turns this into a 404 before
        // CSRF would ever have had a say, and the test would pass vacuously.
        SiteSetting::current()->update(['discord_activity_enabled' => true]);
        SiteSetting::forgetCurrent();

        $this->enforceCsrf();

        $this->postJson(route('activity.comble.token'), [])->assertStatus(422);
    }
}
