<?php

namespace App\Services;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\DailyChallengePick;
use App\Support\DailyGameClock;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * date. The pick is persisted the first time a date is requested
     * (daily_challenge_picks), so it's locked in forever from that point on
     * — immune to queries/characters added later, and even to future changes
     * to the picking algorithm itself, neither of which a re-derived value
     * could ever be safe from.
     *
     * Unlike CombleDailyCombo (which powers a standalone page), this backs a
     * section of the home page, so an empty pair list returns all-null
     * values instead of aborting — the home page must keep working even
     * before any CharacterQuery rows exist.
     */
    public function forDate(CarbonInterface $date): array
    {
        $pick = DailyChallengePick::where('day', $date->toDateString())->first()
            ?? $this->computeAndPersist($date);

        if ($pick === null) {
            return ['query' => null, 'character' => null, 'combo' => null, 'criteria' => []];
        }

        $query = CharacterQuery::with('game')->findOrFail($pick->query_idquery);
        $character = Character::with('game')->findOrFail($pick->character_idcharacter);

        $filters = array_merge($query->filters ?? [], ['characterid' => $character->idcharacter]);
        $combo = $this->searchCombos($character->game, $filters, 1)->first();
        $criteria = $this->describeFilters($character->game, $query->filters ?? [], $character);

        return compact('query', 'character', 'combo', 'criteria');
    }

    /**
     * Batched version of forDate() for a whole date range (both ends
     * inclusive): existing picks are loaded in one query, and any date in
     * the range with no persisted pick yet has one computed (via the same
     * pickPair() used by forDate()) and bulk-inserted with insertOrIgnore —
     * safe against a concurrent forDate()/resultsBetween() call persisting
     * the same date first, which just makes this range's insert for that row
     * a no-op. searchCombos() still runs once per *distinct* (query,
     * character) pair actually picked across the range instead of once per
     * day.
     *
     * Returns a Collection keyed by 'Y-m-d' date string, each value shaped
     * like forDate()'s return (minus 'criteria', which no caller of this
     * batch method needs).
     */
    public function resultsBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $period = CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay());
        $dateStrings = collect($period)->map(fn (CarbonInterface $day) => $day->toDateString());

        $persisted = DailyChallengePick::whereIn('day', $dateStrings)->get()->keyBy('day');

        $pairs = null;
        $newRows = [];

        $picksByDate = $dateStrings->mapWithKeys(function (string $dateString) use ($persisted, &$pairs, &$newRows) {
            if ($persisted->has($dateString)) {
                $row = $persisted[$dateString];

                return [$dateString => ['query_id' => $row->query_idquery, 'character_id' => $row->character_idcharacter]];
            }

            $pairs ??= $this->eligiblePairs();
            $pair = $this->pickPair($pairs, Carbon::parse($dateString, DailyGameClock::TIMEZONE));

            if ($pair !== null) {
                $newRows[] = [
                    'day' => $dateString,
                    'query_idquery' => $pair->query_id,
                    'character_idcharacter' => $pair->character_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            return [$dateString => $pair === null ? null : ['query_id' => $pair->query_id, 'character_id' => $pair->character_id]];
        });

        if ($newRows !== []) {
            DB::table('daily_challenge_picks')->insertOrIgnore($newRows);
        }

        $distinctPicks = $picksByDate->filter()->unique(fn (array $p) => "{$p['query_id']}:{$p['character_id']}");

        $queries = CharacterQuery::with('game')->whereIn('idquery', $distinctPicks->pluck('query_id'))->get()->keyBy('idquery');
        $characters = Character::with('game')->whereIn('idcharacter', $distinctPicks->pluck('character_id'))->get()->keyBy('idcharacter');

        $comboByPair = $distinctPicks->mapWithKeys(function (array $p) use ($queries, $characters) {
            $query = $queries[$p['query_id']];
            $character = $characters[$p['character_id']];
            $filters = array_merge($query->filters ?? [], ['characterid' => $character->idcharacter]);

            return ["{$p['query_id']}:{$p['character_id']}" => $this->searchCombos($character->game, $filters, 1)->first()];
        });

        return $picksByDate->map(function (?array $pick, string $dateString) use ($queries, $characters, $comboByPair) {
            $date = Carbon::parse($dateString, DailyGameClock::TIMEZONE);

            if ($pick === null) {
                return ['date' => $date, 'query' => null, 'character' => null, 'combo' => null];
            }

            $key = "{$pick['query_id']}:{$pick['character_id']}";

            return [
                'date' => $date,
                'query' => $queries[$pick['query_id']],
                'character' => $characters[$pick['character_id']],
                'combo' => $comboByPair[$key],
            ];
        });
    }

    /**
     * Computes the pick for a date that has never been requested before, and
     * persists it. Only reachable the first time a given date is served (see
     * forDate()), so a race between concurrent first requests for the same
     * date is handled by racing on the table's unique `day` index: whichever
     * request's create() lands first wins, the other catches the unique
     * violation and re-reads the now-existing row, so both return the same
     * pair. Returns null (without persisting anything) when the pool is
     * empty for this date — that result is already stable on its own, since
     * nothing created after the date can retroactively become eligible for
     * it, so there's nothing to lock in.
     */
    private function computeAndPersist(CarbonInterface $date): ?DailyChallengePick
    {
        $pair = $this->pickPair($this->eligiblePairs(), $date);

        if ($pair === null) {
            return null;
        }

        try {
            return DailyChallengePick::create([
                'day' => $date->toDateString(),
                'query_idquery' => $pair->query_id,
                'character_idcharacter' => $pair->character_id,
            ]);
        } catch (QueryException) {
            return DailyChallengePick::where('day', $date->toDateString())->first();
        }
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
     *
     * Only the query side is cutoff-protected here, deliberately not the
     * character side too: unlike character_default_queries, `character` rows
     * in the wild can have a NULL created_at (legacy/imported data — Carbon
     * treats null as "now", which would make every such character look
     * newer than any cutoff and permanently exclude it). Persistence (see
     * forDate()/computeAndPersist()) is what actually closes the
     * newly-added-character hole this method used to have — it protects any
     * already-served day regardless of what gets added to the pool
     * afterward, cutoff or not.
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
