<?php

namespace Tests\Feature\Activity;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ActivityAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.client_id' => 'client-id-123',
            'services.discord.client_secret' => 'client-secret-123',
        ]);

        // discord_activity_enabled defaults to false (see
        // EnsureDiscordActivityEnabled) — this suite is about the token
        // exchange's own behavior once turned on.
        SiteSetting::current()->update(['discord_activity_enabled' => true]);
        SiteSetting::forgetCurrent();
    }

    public function test_a_valid_code_returns_a_bearer_token_and_the_discord_access_token(): void
    {
        Http::fake([
            'discord.com/api/v10/oauth2/token' => Http::response(['access_token' => 'discord-access-token'], 200),
            'discord.com/api/v10/users/@me' => Http::response(['id' => '111222333', 'username' => 'testuser'], 200),
        ]);

        $response = $this->postJson(route('activity.comble.token'), ['code' => 'abc123']);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'access_token', 'username']);
        $this->assertSame('discord-access-token', $response->json('access_token'));
        $this->assertSame('testuser', $response->json('username'));

        $payload = json_decode(Crypt::decryptString($response->json('token')), true);
        $this->assertSame('111222333', $payload['uid']);
        $this->assertGreaterThan(now()->timestamp, $payload['exp']);
    }

    public function test_global_name_is_preferred_over_username_when_present(): void
    {
        Http::fake([
            'discord.com/api/v10/oauth2/token' => Http::response(['access_token' => 'discord-access-token'], 200),
            'discord.com/api/v10/users/@me' => Http::response(['id' => '111222333', 'username' => 'testuser', 'global_name' => 'Test User'], 200),
        ]);

        $response = $this->postJson(route('activity.comble.token'), ['code' => 'abc123']);

        $this->assertSame('Test User', $response->json('username'));
    }

    public function test_a_rejected_code_exchange_is_reported_without_a_bearer_token(): void
    {
        Http::fake([
            'discord.com/api/v10/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $response = $this->postJson(route('activity.comble.token'), ['code' => 'expired-or-reused']);

        $response->assertStatus(401);
        $response->assertJsonStructure(['error']);
    }

    public function test_a_failed_users_me_lookup_is_reported_without_a_bearer_token(): void
    {
        Http::fake([
            'discord.com/api/v10/oauth2/token' => Http::response(['access_token' => 'discord-access-token'], 200),
            'discord.com/api/v10/users/@me' => Http::response(['message' => '401: Unauthorized'], 401),
        ]);

        $response = $this->postJson(route('activity.comble.token'), ['code' => 'abc123']);

        $response->assertStatus(401);
        $response->assertJsonStructure(['error']);
    }

    public function test_a_malformed_user_id_is_rejected(): void
    {
        Http::fake([
            'discord.com/api/v10/oauth2/token' => Http::response(['access_token' => 'discord-access-token'], 200),
            'discord.com/api/v10/users/@me' => Http::response(['id' => 'not-a-snowflake'], 200),
        ]);

        $response = $this->postJson(route('activity.comble.token'), ['code' => 'abc123']);

        $response->assertStatus(401);
    }

    public function test_a_missing_code_is_a_validation_error(): void
    {
        $response = $this->postJson(route('activity.comble.token'), []);

        $response->assertStatus(422);
    }

    public function test_the_endpoint_is_gated_behind_the_discord_integration_flag(): void
    {
        SiteSetting::current()->update(['discord_integration_enabled' => false]);
        SiteSetting::forgetCurrent();

        $response = $this->postJson(route('activity.comble.token'), ['code' => 'abc123']);

        $response->assertNotFound();
    }
}
