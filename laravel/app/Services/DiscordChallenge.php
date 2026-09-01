<?php

namespace App\Services;

use App\Support\DailyGameClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DiscordChallenge
{
    public function __construct(private DailyChallenge $dailyChallenge) {}

    /**
     * Handle the `/csk challenge` command and return the Discord interaction
     * response `data` object: the requested day's (default today's)
     * challenge criteria (mirrors the home page's <x-daily-challenge>
     * component) plus the current best-matching combo, if any submission
     * already satisfies it.
     */
    public function handle(array $interactionData = []): array
    {
        $options = $this->flattenOptions($interactionData['options'] ?? []);

        try {
            $day = $this->resolveDate($options['date'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return ['content' => $e->getMessage(), 'flags' => 64];
        }

        $isToday = $day->isSameDay(DailyGameClock::today());
        $dateSuffix = $isToday ? '' : ' — '.$day->format('M j, Y');

        $challenge = $this->dailyChallenge->forDate($day);
        $query = $challenge['query'];
        $character = $challenge['character'];
        $combo = $challenge['combo'];
        $criteria = $challenge['criteria'];

        if (! $query || ! $character) {
            return ['embeds' => [[
                'title' => 'Daily Challenge'.$dateSuffix,
                'description' => $isToday
                    ? 'No challenge is available yet — check back once some default queries are configured.'
                    : 'No challenge was available on this day.',
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
                'title' => "{$character->game->name} — {$character->name} — {$query->label}{$dateSuffix}",
                'description' => $description,
                'fields' => $fields,
            ]],
        ];
    }

    /**
     * Same rules as ChallengeController::resolveDate (no lower bound, since
     * DailyChallenge::forDate is a pure function of the currently-eligible
     * query pool for any given date) except a bad date replies with a
     * friendly ephemeral message instead of a 404 — there's no HTTP route
     * pattern doing format validation upstream here.
     */
    private function resolveDate(?string $date): Carbon
    {
        if (! $date) {
            return DailyGameClock::today();
        }

        try {
            $day = Carbon::createFromFormat('!Y-m-d', $date, DailyGameClock::TIMEZONE);
        } catch (\Throwable) {
            throw new \InvalidArgumentException("\"{$date}\" doesn't look like a date. Use YYYY-MM-DD.");
        }

        if ($day->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException("\"{$date}\" doesn't look like a date. Use YYYY-MM-DD.");
        }

        if ($day->gt(DailyGameClock::today())) {
            throw new \InvalidArgumentException("\"{$date}\" is in the future — that challenge doesn't exist yet.");
        }

        return $day;
    }

    /**
     * Discord nests slash-command sub-command options one level deep
     * (options[0] === the `challenge` sub-command, whose own `options` array
     * holds `date`).
     */
    private function flattenOptions(array $options): array
    {
        if (isset($options[0]['options'])) {
            $options = $options[0]['options'];
        }

        return collect($options)->pluck('value', 'name')->all();
    }
}
