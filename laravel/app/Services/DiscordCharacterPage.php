<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DiscordCharacterPage
{
    /**
     * The deferred-phase work for `/csk character`: resolve the game and
     * character, then PATCH the resulting embed (or an error message) onto
     * the deferred message. Discord only gives 3 seconds for the initial
     * ack (handled by the controller returning `type 5` before this runs),
     * so the resolution queries happen after that ack, dispatched via
     * afterResponse(). Any failure is reported and turned into a plain-text
     * edit of the deferred message instead of leaving it to time out as
     * "The application did not respond".
     */
    public function handle(array $payload): void
    {
        $applicationId = $payload['application_id'] ?? config('services.discord.application_id');
        $token = $payload['token'] ?? null;

        if (! $applicationId || ! $token) {
            return;
        }

        try {
            $options = $this->flattenOptions($payload['data']['options'] ?? []);

            $gameName = $options['game'] ?? null;
            $characterName = $options['character'] ?? null;

            if (! $gameName || ! $characterName) {
                $this->editOriginal($applicationId, $token, ['content' => 'Please provide both a game and a character name.']);

                return;
            }

            $game = $this->resolveGame($gameName);

            if (! $game) {
                $this->editOriginal($applicationId, $token, ['content' => "No game found matching \"{$gameName}\"."]);

                return;
            }

            $character = $this->resolveCharacter($game, $characterName);

            if (! $character) {
                $this->editOriginal($applicationId, $token, ['content' => "No character found matching \"{$characterName}\" in {$game->name}."]);

                return;
            }

            $this->editOriginal($applicationId, $token, ['embeds' => [$this->toEmbed($game, $character)]]);
        } catch (\Throwable $e) {
            report($e);

            $this->editOriginal($applicationId, $token, ['content' => 'Something went wrong looking up that character.']);
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

    private function resolveCharacter(Game $game, string $characterName): ?Character
    {
        $lowerCharacterName = Str::lower($characterName);

        return Character::where('game_idgame', $game->idgame)
            ->whereRaw('LOWER(name) = ?', [$lowerCharacterName])
            ->first()
            ?? Character::where('game_idgame', $game->idgame)
                ->whereHas('aliases', fn ($q) => $q->whereRaw('LOWER(alias) = ?', [$lowerCharacterName]))
                ->first()
            ?? Character::where('game_idgame', $game->idgame)
                ->where('name', 'like', '%'.$characterName.'%')
                ->first()
            ?? Character::where('game_idgame', $game->idgame)
                ->whereHas('aliases', fn ($q) => $q->where('alias', 'like', '%'.$characterName.'%'))
                ->first();
    }

    private function toEmbed(Game $game, Character $character): array
    {
        $embed = [
            'title' => $character->name,
            // Built from config('app.url') rather than route()'s default
            // (request-derived) root, so the link stays the real site even
            // when this endpoint is reached through a tunnel/proxy whose
            // host differs from the public site domain.
            'url' => rtrim(config('app.url'), '/').route('characters.show', [$game, $character], absolute: false),
            'fields' => [
                ['name' => 'Game', 'value' => $game->name, 'inline' => true],
                ['name' => 'Views', 'value' => (string) $character->views, 'inline' => true],
            ],
        ];

        if ($character->imageUrl) {
            $embed['thumbnail'] = ['url' => $character->imageUrl];
        }

        return $embed;
    }

    /**
     * Discord nests slash-command sub-command options one level deep
     * (data.options[0] === the `character` sub-command, whose own `options`
     * array holds `game`/`character`); unwrap that before reading values.
     */
    private function flattenOptions(array $options): array
    {
        if (isset($options[0]['options'])) {
            $options = $options[0]['options'];
        }

        return collect($options)->pluck('value', 'name')->all();
    }

    private function editOriginal(string $applicationId, string $token, array $data): void
    {
        Http::asJson()->timeout(10)->patch(
            "https://discord.com/api/v10/webhooks/{$applicationId}/{$token}/messages/@original",
            $data
        );
    }
}
