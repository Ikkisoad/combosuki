<?php

namespace App\Services;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DiscordComboSearch
{
    use FiltersCombos;

    public function __construct(private VideoEmbedResolver $videoResolver) {}

    /**
     * Handle the text-based `/csk search` interaction's `data` payload and
     * return the Discord interaction response `data` object. $channelId
     * (from the interaction envelope, not `data`) is passed through to
     * runSearch() — see its docblock.
     */
    public function handle(array $interactionData, ?string $channelId = null): array
    {
        $options = $this->flattenOptions($interactionData['options'] ?? []);

        $gameName = $options['game'] ?? null;
        $queryText = $options['query'] ?? null;
        $characterName = $options['character'] ?? null;

        if (! $gameName) {
            return $this->ephemeral('Please provide a game name.');
        }

        $lowerGameName = Str::lower($gameName);

        $game = Game::whereRaw('LOWER(name) = ?', [$lowerGameName])->first()
            ?? Game::whereHas('aliases', fn ($q) => $q->whereRaw('LOWER(alias) = ?', [$lowerGameName]))->first()
            ?? Game::where('name', 'like', '%'.$gameName.'%')->first()
            ?? Game::whereHas('aliases', fn ($q) => $q->where('alias', 'like', '%'.$gameName.'%'))->first();

        if (! $game) {
            return $this->ephemeral("No game found matching \"{$gameName}\".");
        }

        $characterId = null;

        if ($characterName) {
            $lowerCharacterName = Str::lower($characterName);

            $character = Character::where('game_idgame', $game->idgame)
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

            if (! $character) {
                return $this->ephemeral("No character found matching \"{$characterName}\" in {$game->name}.");
            }

            $characterId = $character->idcharacter;
        }

        return $this->runSearch($game, [
            'combo' => $queryText,
            'characterid' => $characterId,
        ], $channelId);
    }

    /**
     * Run a combo search for $game with $filters (a flat map of FiltersCombos
     * field names — `combo`, `characterid`, and any primary resource's
     * text_name with spaces replaced by underscores — to values) and return
     * the Discord interaction response `data` object. Shared by the
     * text-based `/csk search` command and DiscordComboWizard's dropdown
     * flow, so both stay behind one query-building/response-formatting path.
     *
     * $channelId, when given, is used to post a real bot channel message
     * with the top result's video URL (see postVideoFollowUp()) when there
     * is one — Discord doesn't unfurl plain URLs placed in an interaction
     * response's `content` the way it does for a normal message, so a real
     * playable video card requires an actual follow-up message; that only
     * makes sense to do once per response, hence showing just the top match
     * when a video is present.
     */
    public function runSearch(Game $game, array $filters, ?string $channelId = null): array
    {
        $combos = $this->searchCombos($game, $filters, 5);

        if ($combos->isEmpty()) {
            return $this->ephemeral('No combos found.');
        }

        $best = $combos->first();

        if ($channelId !== null && $this->videoResolver->resolve($best->video) !== null) {
            $this->postVideoFollowUp($channelId, $best->video);

            return ['embeds' => [$this->toEmbed($best, includeVideoField: false)]];
        }

        return ['embeds' => $combos->map(fn (Combo $combo) => $this->toEmbed($combo))->all()];
    }

    /**
     * Discord nests slash-command sub-command options one level deep
     * (data.options[0] === the `search` sub-command, whose own `options`
     * array holds `game`/`query`/`character`); unwrap that before reading
     * values.
     */
    private function flattenOptions(array $options): array
    {
        if (isset($options[0]['options'])) {
            $options = $options[0]['options'];
        }

        return collect($options)->pluck('value', 'name')->all();
    }

    private function toEmbed(Combo $combo, bool $includeVideoField = true): array
    {
        $fields = [
            ['name' => 'Character', 'value' => $combo->character->name ?? 'Unknown', 'inline' => true],
        ];

        if ($combo->damage !== null) {
            $fields[] = ['name' => 'Damage', 'value' => (string) $combo->damage, 'inline' => true];
        }

        if ($combo->patch) {
            $fields[] = ['name' => 'Patch', 'value' => $combo->patch->label, 'inline' => true];
        }

        if ($includeVideoField && $this->videoResolver->resolve($combo->video) !== null) {
            $fields[] = ['name' => 'Video', 'value' => $combo->video, 'inline' => false];
        }

        return [
            'title' => Str::limit($combo->combo, 256, ''),
            // Built from config('app.url') rather than route()'s default
            // (request-derived) root, so the link stays the real site even
            // when this endpoint is reached through a tunnel/proxy whose
            // host differs from the public site domain.
            'url' => rtrim(config('app.url'), '/').route('combos.show', $combo, absolute: false),
            'fields' => $fields,
        ];
    }

    /**
     * Post the video URL as a genuine bot-authored channel message (as
     * opposed to interaction response content) so Discord's normal message
     * pipeline unfurls it into a real playable embed. Best-effort: the bot
     * may not have been invited with the `bot` scope / Send Messages
     * permission in every server, so a failure here shouldn't break the
     * interaction response itself.
     */
    private function postVideoFollowUp(string $channelId, string $videoUrl): void
    {
        $botToken = config('services.discord.bot_token');

        if (! $botToken) {
            return;
        }

        Http::withToken($botToken, 'Bot')
            ->asJson()
            ->post("https://discord.com/api/v10/channels/{$channelId}/messages", [
                'content' => $videoUrl,
            ]);
    }

    private function ephemeral(string $content): array
    {
        return ['content' => $content, 'flags' => 64];
    }
}
