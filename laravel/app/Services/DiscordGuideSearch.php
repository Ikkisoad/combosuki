<?php

namespace App\Services;

use App\Models\Game;
use App\Models\ListModel;
use Illuminate\Support\Str;

class DiscordGuideSearch
{
    /**
     * Handle the text-based `/csk guide` interaction's `data` payload and
     * return the Discord interaction response `data` object. Both the
     * `game` and `name` options are optional — with neither given, this
     * returns the top 3 guides by views across every game.
     */
    public function handle(array $interactionData): array
    {
        $options = $this->flattenOptions($interactionData['options'] ?? []);

        $gameName = $options['game'] ?? null;
        $name = $options['name'] ?? null;

        $query = ListModel::with('game')
            ->where('type', '!=', 0)
            ->where('is_favorite_guide', false)
            ->orderByDesc('views')
            ->orderByDesc('idlist')
            ->limit(3);

        if ($gameName) {
            $game = $this->resolveGame($gameName);

            if (! $game) {
                return $this->ephemeral("No game found matching \"{$gameName}\".");
            }

            $query->where('game_idgame', $game->idgame);
        }

        if ($name) {
            $query->where('list_name', 'like', '%'.$name.'%');
        }

        $guides = $query->get();

        if ($guides->isEmpty()) {
            return $this->ephemeral('No guides found.');
        }

        return ['embeds' => $guides->map(fn (ListModel $guide) => $this->toEmbed($guide))->all()];
    }

    private function resolveGame(string $gameName): ?Game
    {
        $lowerGameName = Str::lower($gameName);

        return Game::whereRaw('LOWER(name) = ?', [$lowerGameName])->first()
            ?? Game::whereHas('aliases', fn ($q) => $q->whereRaw('LOWER(alias) = ?', [$lowerGameName]))->first()
            ?? Game::where('name', 'like', '%'.$gameName.'%')->first()
            ?? Game::whereHas('aliases', fn ($q) => $q->where('alias', 'like', '%'.$gameName.'%'))->first();
    }

    /**
     * Discord nests slash-command sub-command options one level deep
     * (data.options[0] === the `guide` sub-command, whose own `options`
     * array holds `game`/`name`); unwrap that before reading values.
     */
    private function flattenOptions(array $options): array
    {
        if (isset($options[0]['options'])) {
            $options = $options[0]['options'];
        }

        return collect($options)->pluck('value', 'name')->all();
    }

    private function toEmbed(ListModel $guide): array
    {
        $fields = [
            ['name' => 'Game', 'value' => $guide->game->name ?? 'Unknown', 'inline' => true],
            ['name' => 'Views', 'value' => (string) $guide->views, 'inline' => true],
        ];

        return [
            'title' => Str::limit($guide->list_name, 256, ''),
            // Built from config('app.url') rather than route()'s default
            // (request-derived) root, so the link stays the real site even
            // when this endpoint is reached through a tunnel/proxy whose
            // host differs from the public site domain.
            'url' => rtrim(config('app.url'), '/').route('lists.show', $guide, absolute: false),
            'fields' => $fields,
        ];
    }

    private function ephemeral(string $content): array
    {
        return ['content' => $content, 'flags' => 64];
    }
}
