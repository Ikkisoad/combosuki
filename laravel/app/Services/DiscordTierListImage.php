<?php

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DiscordTierListImage
{
    public function __construct(
        private TierListAggregator $aggregator,
        private TierListImageRenderer $renderer,
    ) {}

    /**
     * Discord nests slash-command sub-command options one level deep
     * (data.options[0] === the `tierlist` sub-command, whose own `options`
     * array holds `game`/`from`/`to`). Public so the controller can validate
     * the date options synchronously, before deferring, without duplicating
     * this unwrapping.
     */
    public function extractOptions(array $payload): array
    {
        $options = $payload['data']['options'] ?? [];

        if (isset($options[0]['options'])) {
            $options = $options[0]['options'];
        }

        return collect($options)->pluck('value', 'name')->all();
    }

    public function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            throw new \InvalidArgumentException("\"{$value}\" doesn't look like a date.");
        }
    }

    /**
     * The deferred-phase work for `/csk tierlist`: resolve the game, run the
     * aggregation, render the GD image, and PATCH it onto the deferred
     * message. Discord only gives 3 seconds for the initial ack (handled by
     * the controller returning `type 5` before this runs), so everything
     * here — DB queries, portrait file reads, GD compositing, the multipart
     * upload — happens after that ack, dispatched via afterResponse(). Any
     * failure is reported and turned into a plain-text edit of the deferred
     * message instead of leaving it to time out as "The application did not
     * respond".
     */
    public function handle(array $payload): void
    {
        $applicationId = $payload['application_id'] ?? config('services.discord.application_id');
        $token = $payload['token'] ?? null;

        if (! $applicationId || ! $token) {
            return;
        }

        try {
            $options = $this->extractOptions($payload);
            $gameName = $options['game'] ?? '';
            $from = $this->parseDate($options['from'] ?? null);
            $to = $this->parseDate($options['to'] ?? null);

            $game = $this->resolveGame($gameName);

            if (! $game) {
                $this->editOriginal($applicationId, $token, ['content' => "No game found matching \"{$gameName}\"."]);

                return;
            }

            $aggregate = $this->aggregator->aggregate($game, $from, $to);

            if ($aggregate['tierListCount'] === 0) {
                $this->editOriginal($applicationId, $token, ['content' => "No tier lists have been submitted for {$game->name} yet."]);

                return;
            }

            $png = $this->renderer->render($aggregate, $game->name, $from, $to);

            $this->editOriginalWithImage($applicationId, $token, $game->name, $aggregate['tierListCount'], $png);
        } catch (\Throwable $e) {
            report($e);

            $this->editOriginal($applicationId, $token, ['content' => 'Something went wrong generating the tier list image.']);
        }
    }

    private function resolveGame(string $gameName): ?Game
    {
        $lowerGameName = Str::lower($gameName);

        return Game::whereRaw('LOWER(name) = ?', [$lowerGameName])->first()
            ?? Game::whereHas('aliases', fn ($q) => $q->whereRaw('LOWER(alias) = ?', [$lowerGameName]))->first()
            ?? Game::where('name', 'like', '%'.$gameName.'%')->first()
            ?? Game::whereHas('aliases', fn ($q) => $q->where('alias', 'like', '%'.$gameName.'%'))->first();
    }

    private function editOriginal(string $applicationId, string $token, array $data): void
    {
        Http::asJson()->timeout(10)->patch(
            "https://discord.com/api/v10/webhooks/{$applicationId}/{$token}/messages/@original",
            $data
        );
    }

    private function editOriginalWithImage(string $applicationId, string $token, string $gameName, int $tierListCount, string $png): void
    {
        $payload = [
            'embeds' => [[
                'title' => "{$gameName} — Tier List",
                'description' => 'Based on '.$tierListCount.' tier list'.($tierListCount === 1 ? '' : 's'),
                'image' => ['url' => 'attachment://tierlist.png'],
            ]],
            'attachments' => [],
        ];

        Http::timeout(30)
            ->attach('files[0]', $png, 'tierlist.png', ['Content-Type' => 'image/png'])
            ->patch(
                "https://discord.com/api/v10/webhooks/{$applicationId}/{$token}/messages/@original",
                ['payload_json' => json_encode($payload)]
            );
    }
}
