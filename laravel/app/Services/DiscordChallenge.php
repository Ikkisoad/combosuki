<?php

namespace App\Services;

use Illuminate\Support\Str;

class DiscordChallenge
{
    public function __construct(private DailyChallenge $dailyChallenge) {}

    /**
     * Handle the `/challenge` command and return the Discord interaction
     * response `data` object: today's challenge criteria (mirrors the home
     * page's <x-daily-challenge> component) plus the current best-matching
     * combo, if any submission already satisfies it.
     */
    public function handle(): array
    {
        $challenge = $this->dailyChallenge->today();
        $query = $challenge['query'];
        $character = $challenge['character'];
        $combo = $challenge['combo'];
        $criteria = $challenge['criteria'];

        if (! $query || ! $character) {
            return ['embeds' => [[
                'title' => 'Daily Challenge',
                'description' => 'No challenge is available yet — check back once some default queries are configured.',
            ]]];
        }

        $requirements = collect(array_merge(["Character: {$character->name}"], $criteria))
            ->map(fn (string $line) => "- {$line}")
            ->implode("\n");

        $description = "**A qualifying combo must satisfy:**\n{$requirements}";

        $fields = [];

        if ($combo) {
            $fields[] = ['name' => 'Current winning combo', 'value' => Str::limit($combo->combo, 1024, ''), 'inline' => false];

            if ($combo->damage !== null) {
                $fields[] = ['name' => 'Damage', 'value' => (string) $combo->damage, 'inline' => true];
            }

            $url = rtrim(config('app.url'), '/').route('combos.show', $combo, absolute: false);
            $description .= "\n\n[Think you can do better? Go beat it]({$url})";
        } else {
            $url = rtrim(config('app.url'), '/').route('games.combos.create', $character->game, absolute: false)
                ."?query={$query->idquery}&characterid={$character->idcharacter}";
            $description .= "\n\nNo combo found for this challenge yet — [be the first to submit one]({$url})!";
        }

        return [
            'embeds' => [[
                'title' => "{$character->game->name} — {$character->name} — {$query->label}",
                'description' => $description,
                'fields' => $fields,
            ]],
        ];
    }
}
