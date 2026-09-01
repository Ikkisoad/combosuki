<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\Support\Collection;
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

            $combos = Combo::where('character_idcharacter', $character->idcharacter)
                ->visibleTo(auth()->user())
                ->orderByDesc('damage')
                ->limit(3)
                ->get();

            $this->editOriginal($applicationId, $token, ['embeds' => [$this->toEmbed($game, $character, $combos)]]);
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

    /**
     * $combos is pre-fetched by handle() (visibility-scoped, top damage
     * first) rather than queried in here, so this stays a pure formatter and
     * can be exercised by the Unit test without a database.
     */
    private function toEmbed(Game $game, Character $character, Collection $combos): array
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
                // Discord rejects an embed field with an empty-string value,
                // so a null `views` (schema drift, a column missing on a given
                // environment) falls back to 0 instead of silently failing
                // the edit and leaving the deferred message stuck "thinking".
                ['name' => 'Views', 'value' => (string) ($character->views ?? 0), 'inline' => true],
            ],
        ];

        // Mirrors the "Top Damage Combos" section of the character's page
        // (characters.show), so the embed doesn't just point at the page but
        // actually previews what's on it. Omitted (like the thumbnail below)
        // rather than shown empty when the character has no visible combos.
        if ($combos->isNotEmpty()) {
            $embed['fields'][] = [
                'name' => 'Top Combos',
                'value' => $combos->map(fn (Combo $combo) => Str::limit($combo->combo, 100, '').' — '.(
                    $combo->damage !== null ? number_format((float) $combo->damage, 0, '', '.').' dmg' : 'no damage listed'
                ))->implode("\n"),
                'inline' => false,
            ];
        }

        // `image` can hold a legacy free-text URL from before uploads
        // existed (see Character::imageUrl), so it isn't guaranteed to be a
        // well-formed absolute URL — Discord rejects the whole embed if it
        // isn't, so a malformed one is dropped instead of breaking the reply.
        if ($character->imageUrl && filter_var($character->imageUrl, FILTER_VALIDATE_URL)) {
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

    /**
     * A failed edit here (e.g. Discord 400-ing an invalid embed) has no
     * further fallback to escalate to — the interaction is left stuck on
     * "thinking" either way — so it's logged rather than silently ignored,
     * to make that failure mode visible instead of indistinguishable from
     * this method never having run at all.
     */
    private function editOriginal(string $applicationId, string $token, array $data): void
    {
        $response = Http::asJson()->timeout(10)->patch(
            "https://discord.com/api/v10/webhooks/{$applicationId}/{$token}/messages/@original",
            $data
        );

        if ($response->failed()) {
            report(new \RuntimeException("Discord rejected the /csk character follow-up edit: {$response->status()} {$response->body()}"));
        }
    }
}
