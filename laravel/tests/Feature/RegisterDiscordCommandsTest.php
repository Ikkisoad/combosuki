<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegisterDiscordCommandsTest extends TestCase
{
    private const COMMANDS_URL = 'https://discord.com/api/v10/applications/app-123/commands';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.application_id' => 'app-123',
            'services.discord.bot_token' => 'bot-token-123',
            'services.discord.guild_id' => null,
        ]);
    }

    /**
     * Discord auto-manages a global "Primary Entry Point" command once
     * Activities are enabled, and rejects (error 50240) a bulk overwrite of
     * the global command set that would drop it — see
     * RegisterDiscordCommands::existingEntryPointCommand()'s docblock. This
     * asserts the command fetches and re-includes it rather than
     * regressing back to a bulk PUT that only carries /csk.
     */
    public function test_a_global_registration_preserves_the_existing_entry_point_command(): void
    {
        $entryPoint = ['id' => '999', 'type' => 4, 'name' => 'Launch Activity', 'handler' => 2];

        Http::fake(function ($request) use ($entryPoint) {
            if ($request->url() !== self::COMMANDS_URL) {
                return Http::response(['message' => 'unexpected url'], 404);
            }

            return match ($request->method()) {
                'GET' => Http::response([['id' => '1', 'type' => 1, 'name' => 'csk'], $entryPoint], 200),
                'PUT' => Http::response($request->data(), 200),
                default => Http::response(['message' => 'unexpected method'], 405),
            };
        });

        $this->artisan('discord:register-commands')->assertSuccessful();

        Http::assertSent(function ($request) use ($entryPoint) {
            return $request->url() === self::COMMANDS_URL
                && $request->method() === 'PUT'
                && collect($request->data())->contains(fn ($command) => ($command['name'] ?? null) === 'csk')
                && collect($request->data())->contains($entryPoint);
        });
    }

    /** Entry point commands don't exist at the guild scope, so a guild registration must never even fetch one. */
    public function test_a_guild_registration_does_not_fetch_or_include_an_entry_point_command(): void
    {
        Http::fake([
            'discord.com/api/v10/applications/app-123/guilds/guild-456/commands' => Http::response([], 200),
        ]);

        $this->artisan('discord:register-commands', ['--guild' => 'guild-456'])->assertSuccessful();

        Http::assertNotSent(fn ($request) => $request->method() === 'GET');

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && ! collect($request->data())->contains(fn ($command) => ($command['type'] ?? null) === 4));
    }

    /** A failed lookup degrades to registering without the entry point — surfaced by Discord's own rejection, not swallowed silently. */
    public function test_a_failed_entry_point_lookup_still_attempts_registration(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(['message' => 'server error'], 500);
            }

            return Http::response(['message' => 'entry point command is required'], 400);
        });

        $this->artisan('discord:register-commands')->assertFailed();
    }
}
