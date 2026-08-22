<?php

namespace App\Services;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Models\Character;
use App\Models\CharacterQuery;
use Carbon\CarbonInterface;

class DailyChallenge
{
    use FiltersCombos;

    public function today(): array
    {
        return $this->forDate(now());
    }

    /**
     * Picks the same (query, character) pair for every visitor on a given
     * date, without persisting anything: the eligible pair list is
     * deterministic (ordered by idquery then idcharacter) and the index into
     * it is derived from a hash of the date, mirroring CombleDailyCombo.
     *
     * Unlike CombleDailyCombo (which powers a standalone page), this backs a
     * section of the home page, so an empty pair list returns all-null
     * values instead of aborting — the home page must keep working even
     * before any CharacterQuery rows exist.
     */
    public function forDate(CarbonInterface $date): array
    {
        $pairs = CharacterQuery::query()
            ->join('character', 'character.game_idgame', '=', 'character_default_queries.game_idgame')
            ->join('game', 'game.idgame', '=', 'character.game_idgame')
            ->where('game.complete', '>', 0)
            ->orderBy('character_default_queries.idquery')
            ->orderBy('character.idcharacter')
            ->get([
                'character_default_queries.idquery as query_id',
                'character.idcharacter as character_id',
            ]);

        if ($pairs->isEmpty()) {
            return ['query' => null, 'character' => null, 'combo' => null];
        }

        $seed = hexdec(substr(hash('sha256', $date->toDateString()), 0, 8));
        $pair = $pairs[$seed % $pairs->count()];

        $query = CharacterQuery::with('game')->findOrFail($pair->query_id);
        $character = Character::with('game')->findOrFail($pair->character_id);

        $filters = array_merge($query->filters ?? [], ['characterid' => $character->idcharacter]);
        $combo = $this->searchCombos($character->game, $filters, 1)->first();

        return compact('query', 'character', 'combo');
    }
}
