<?php

namespace App\Services;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Models\Character;
use App\Models\CharacterQuery;
use App\Support\DailyGameClock;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        $earliestCreatedAt = $this->eligibleQuery()->min('character_default_queries.created_at');

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
        $pair = $this->pickPair($this->eligiblePairs(), $date);

        if ($pair === null) {
            return ['query' => null, 'character' => null, 'combo' => null, 'criteria' => []];
        }

        $query = CharacterQuery::with('game')->findOrFail($pair->query_id);
        $character = Character::with('game')->findOrFail($pair->character_id);

        $filters = array_merge($query->filters ?? [], ['characterid' => $character->idcharacter]);
        $combo = $this->searchCombos($character->game, $filters, 1)->first();
        $criteria = $this->describeFilters($character->game, $query->filters ?? [], $character);

        return compact('query', 'character', 'combo', 'criteria');
    }

    /**
     * Batched version of forDate() for a whole date range (both ends
     * inclusive): the eligible pool barely changes day to day (only when a
     * CharacterQuery is added), so it's fetched once here instead of once
     * per day, and searchCombos() runs once per *distinct* (query,
     * character) pair actually picked across the range instead of once per
     * day — turning what would be O(days) queries into roughly
     * O(1 + distinct pairs used).
     *
     * Returns a Collection keyed by 'Y-m-d' date string, each value shaped
     * like forDate()'s return (minus 'criteria', which no caller of this
     * batch method needs).
     */
    public function resultsBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $pairs = $this->eligiblePairs();

        $period = CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay());

        $picks = collect($period)->mapWithKeys(
            fn (CarbonInterface $day) => [$day->toDateString() => $this->pickPair($pairs, $day)]
        );

        $distinctPairs = $picks->filter()->unique(fn ($pair) => "{$pair->query_id}:{$pair->character_id}");

        $queries = CharacterQuery::with('game')->whereIn('idquery', $distinctPairs->pluck('query_id'))->get()->keyBy('idquery');
        $characters = Character::with('game')->whereIn('idcharacter', $distinctPairs->pluck('character_id'))->get()->keyBy('idcharacter');

        $comboByPair = $distinctPairs->mapWithKeys(function ($pair) use ($queries, $characters) {
            $query = $queries[$pair->query_id];
            $character = $characters[$pair->character_id];
            $filters = array_merge($query->filters ?? [], ['characterid' => $character->idcharacter]);

            return ["{$pair->query_id}:{$pair->character_id}" => $this->searchCombos($character->game, $filters, 1)->first()];
        });

        return $picks->map(function (?object $pair, string $dateString) use ($queries, $characters, $comboByPair) {
            $date = Carbon::parse($dateString, DailyGameClock::TIMEZONE);

            if ($pair === null) {
                return ['date' => $date, 'query' => null, 'character' => null, 'combo' => null];
            }

            return [
                'date' => $date,
                'query' => $queries[$pair->query_id],
                'character' => $characters[$pair->character_id],
                'combo' => $comboByPair["{$pair->query_id}:{$pair->character_id}"],
            ];
        });
    }

    private function eligibleQuery(): Builder
    {
        return CharacterQuery::query()
            ->join('character', 'character.game_idgame', '=', 'character_default_queries.game_idgame')
            ->join('game', 'game.idgame', '=', 'character.game_idgame')
            ->where('game.complete', '>', 0);
    }

    private function eligiblePairs(): Collection
    {
        return $this->eligibleQuery()
            ->orderBy('character_default_queries.idquery')
            ->orderBy('character.idcharacter')
            ->get([
                'character_default_queries.idquery as query_id',
                'character.idcharacter as character_id',
                'character_default_queries.created_at',
            ]);
    }

    /**
     * $eligible->values() re-indexes after filter(): Collection::filter()
     * preserves the original keys, so indexing by $seed % count() against
     * the un-re-indexed collection could hit a missing key or silently
     * select the wrong pair.
     */
    private function pickPair(Collection $pairs, CarbonInterface $date): ?object
    {
        $eligible = $pairs
            ->filter(fn ($pair) => $pair->created_at->lt($date->copy()->setTimezone('UTC')))
            ->values();

        if ($eligible->isEmpty()) {
            return null;
        }

        $seed = hexdec(substr(hash('sha256', $date->toDateString()), 0, 8));

        return $eligible[$seed % $eligible->count()];
    }
}
