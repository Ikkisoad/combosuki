<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Game;
use Illuminate\Support\Str;

class DiscordCharacterPage
{
    /**
     * Handle the text-based `/csk character` interaction's `data` payload
     * and return the Discord interaction response `data` object: an embed
     * linking to the character's page, with their portrait as a thumbnail.
     */
    public function handle(array $interactionData): array
    {
        $options = $this->flattenOptions($interactionData['options'] ?? []);

        $gameName = $options['game'] ?? null;
        $characterName = $options['character'] ?? null;

        if (! $gameName || ! $characterName) {
            return $this->ephemeral('Please provide both a game and a character name.');
        }

        $game = $this->resolveGame($gameName);

        if (! $game) {
            return $this->ephemeral("No game found matching \"{$gameName}\".");
        }

        $character = $this->resolveCharacter($game, $characterName);

        if (! $character) {
            return $this->ephemeral("No character found matching \"{$characterName}\" in {$game->name}.");
        }

        return ['embeds' => [$this->toEmbed($game, $character)]];
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

    private function ephemeral(string $content): array
    {
        return ['content' => $content, 'flags' => 64];
    }
}
