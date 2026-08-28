<?php

namespace App\Services;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Models\Character;
use App\Models\CharacterQuery;
use App\Support\DailyGameClock;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DailyChallenge
{
    use FiltersCombos;

    public function today(): array
    {
        return $this->forDate(DailyGameClock::today());
    }

    /**
     * The first date whose challenge pool is non-empty, or null if no
     * eligible query/character pair exists yet. Mirrors the eligibility
     * join in forDate() (minus its date cutoff): a query created on day X
     * only enters the pool starting day X+1, so the earliest usable day is
     * always the calendar day (in DailyGameClock's timezone) after the
     * earliest eligible query's creation, never that day itself.
     */
    public function earliestDate(): ?CarbonInterface
    {
        $earliestCreatedAt = CharacterQuery::query()
            ->join('character', 'character.game_idgame', '=', 'character_default_queries.game_idgame')
            ->join('game', 'game.idgame', '=', 'character.game_idgame')
            ->where('game.complete', '>', 0)
            ->min('character_default_queries.created_at');

        if ($earliestCreatedAt === null) {
            return null;
        }

        return Carbon::parse($earliestCreatedAt, 'UTC')
            ->setTimezone(DailyGameClock::TIMEZONE)
            ->startOfDay()
            ->addDay();
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
     *
     * Queries created on or after the challenge date are excluded from the
     * pool: since the pool's size drives `$seed % $pairs->count()`, adding a
     * new default query would otherwise shift the pick for every date
     * (past, present, and future) the instant it's saved.
     */
    public function forDate(CarbonInterface $date): array
    {
        $pairs = CharacterQuery::query()
            ->join('character', 'character.game_idgame', '=', 'character_default_queries.game_idgame')
            ->join('game', 'game.idgame', '=', 'character.game_idgame')
            ->where('game.complete', '>', 0)
            ->where('character_default_queries.created_at', '<', $date->copy()->setTimezone('UTC'))
            ->orderBy('character_default_queries.idquery')
            ->orderBy('character.idcharacter')
            ->get([
                'character_default_queries.idquery as query_id',
                'character.idcharacter as character_id',
            ]);

        if ($pairs->isEmpty()) {
            return ['query' => null, 'character' => null, 'combo' => null, 'criteria' => []];
        }

        $seed = hexdec(substr(hash('sha256', $date->toDateString()), 0, 8));
        $pair = $pairs[$seed % $pairs->count()];

        $query = CharacterQuery::with('game')->findOrFail($pair->query_id);
        $character = Character::with('game')->findOrFail($pair->character_id);

        $filters = array_merge($query->filters ?? [], ['characterid' => $character->idcharacter]);
        $combo = $this->searchCombos($character->game, $filters, 1)->first();
        $criteria = $this->describeFilters($character->game, $query->filters ?? []);

        return compact('query', 'character', 'combo', 'criteria');
    }
}
