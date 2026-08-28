<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RegisterDiscordCommands extends Command
{
    protected $signature = 'discord:register-commands {--guild= : Register to a single guild for instant propagation instead of globally}';

    protected $description = 'Register the /csk search, guide, browse, comble, challenge, and submit slash commands with Discord';

    public function handle(): int
    {
        $applicationId = config('services.discord.application_id');
        $botToken = config('services.discord.bot_token');

        if (! $applicationId || ! $botToken) {
            $this->error('DISCORD_APPLICATION_ID and DISCORD_BOT_TOKEN must be set.');

            return self::FAILURE;
        }

        $guild = $this->option('guild') ?: config('services.discord.guild_id');

        $url = $guild
            ? "https://discord.com/api/v10/applications/{$applicationId}/guilds/{$guild}/commands"
            : "https://discord.com/api/v10/applications/{$applicationId}/commands";

        $commands = [
            [
                'name' => 'csk',
                'description' => 'Search the combo database',
                'type' => 1,
                'options' => [
                    [
                        'name' => 'search',
                        'description' => 'Search for combos in a game',
                        'type' => 1,
                        'options' => [
                            [
                                'name' => 'game',
                                'description' => 'Game name',
                                'type' => 3,
                                'required' => true,
                            ],
                            [
                                'name' => 'query',
                                'description' => 'Combo text to search for',
                                'type' => 3,
                                'required' => true,
                            ],
                            [
                                'name' => 'character',
                                'description' => 'Character name to filter by',
                                'type' => 3,
                                'required' => false,
                            ],
                        ],
                    ],
                    [
                        'name' => 'guide',
                        'description' => 'Search for guides by game and/or name',
                        'type' => 1,
                        'options' => [
                            [
                                'name' => 'game',
                                'description' => 'Game name',
                                'type' => 3,
                                'required' => false,
                            ],
                            [
                                'name' => 'name',
                                'description' => 'Guide name to search for',
                                'type' => 3,
                                'required' => false,
                            ],
                        ],
                    ],
                    [
                        'name' => 'browse',
                        'description' => 'Search combos step by step with dropdowns',
                        'type' => 1,
                    ],
                    [
                        'name' => 'comble',
                        'description' => 'Play today\'s Comble puzzle — guess the combo behind the mystery notation',
                        'type' => 1,
                    ],
                    [
                        'name' => 'challenge',
                        'description' => 'Show today\'s daily challenge and its current winning combo, if any',
                        'type' => 1,
                    ],
                    [
                        'name' => 'submit',
                        'description' => 'Submit a new combo (requires a linked Discord account)',
                        'type' => 1,
                    ],
                ],
            ],
        ];

        // Once the application has an Activity enabled (see
        // routes/activity.php), Discord auto-manages a global "Primary Entry
        // Point" command (type 4) that launches it from the Apps menu.
        // Entry point commands don't exist at the guild scope, and a global
        // bulk overwrite replaces the *entire* command set — Discord
        // rejects (rather than silently drops) an update that would remove
        // that command via this endpoint, so it has to be fetched and
        // included unchanged in every global registration alongside our own
        // commands. A no-op if the application has no entry point command
        // (e.g. Activities isn't enabled).
        if (! $guild) {
            $entryPoint = $this->existingEntryPointCommand($applicationId, $botToken);

            if ($entryPoint) {
                $commands[] = $entryPoint;
            }
        }

        $response = Http::withToken($botToken, 'Bot')->asJson()->put($url, $commands);

        if ($response->failed()) {
            $this->error('Discord API error: '.$response->body());

            return self::FAILURE;
        }

        $this->info($guild
            ? "Registered guild command for guild {$guild}."
            : 'Registered global command (may take up to 1 hour to propagate).');

        return self::SUCCESS;
    }

    /** @return array<string, mixed>|null */
    private function existingEntryPointCommand(string $applicationId, string $botToken): ?array
    {
        $response = Http::withToken($botToken, 'Bot')->get("https://discord.com/api/v10/applications/{$applicationId}/commands");

        if ($response->failed()) {
            $this->warn("Couldn't fetch existing commands to preserve the Entry Point command: ".$response->body());

            return null;
        }

        return collect($response->json())->first(fn ($command) => ($command['type'] ?? null) === 4);
    }
}
