<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RegisterDiscordCommands extends Command
{
    protected $signature = 'discord:register-commands {--guild= : Register to a single guild for instant propagation instead of globally}';

    protected $description = 'Register the /csk search, guide, browse, comble, and challenge slash commands with Discord';

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

        $response = Http::withToken($botToken, 'Bot')->asJson()->put($url, [
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
                ],
            ],
        ]);

        if ($response->failed()) {
            $this->error('Discord API error: '.$response->body());

            return self::FAILURE;
        }

        $this->info($guild
            ? "Registered guild command for guild {$guild}."
            : 'Registered global command (may take up to 1 hour to propagate).');

        return self::SUCCESS;
    }
}
