<?php

namespace App\Services;

use App\Models\Game;
use App\Models\TierListEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TierListAggregator
{
    public function aggregate(Game $game, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $tierRank = array_flip(TierListEntry::TIERS);
        $maxRank = count(TierListEntry::TIERS) - 1;

        $entries = TierListEntry::query()
            ->with('character', 'resourceValue.characterAliases')
            ->whereHas('tierList', function ($query) use ($game, $from, $to) {
                $query->where('game_idgame', $game->idgame);

                if ($from) {
                    $query->where('created_at', '>=', $from->copy()->startOfDay());
                }

                if ($to) {
                    $query->where('created_at', '<=', $to->copy()->endOfDay());
                }
            })
            ->get();

        $tierListCount = $entries->pluck('tier_list_idtier_list')->unique()->count();

        // Within a single tier list's tier, a character's drag position (best
        // first) is folded into a fractional offset in [0, 1). This never
        // moves a character into a different tier bucket by itself — it only
        // breaks ties for display order among characters the community
        // already agreed belong in the same tier.
        $positionOffsets = [];

        foreach ($entries->groupBy(fn ($entry) => $entry->tier_list_idtier_list.'|'.$entry->tier) as $group) {
            $sorted = $group->sortBy('order')->values();
            $count = $sorted->count();

            foreach ($sorted as $index => $entry) {
                $positionOffsets[$entry->idtier_list_entry] = $count > 1 ? $index / $count : 0.0;
            }
        }

        $characters = $entries->groupBy(fn ($entry) => $entry->character_idcharacter.'|'.($entry->resources_values_idResources_values ?? ''))
            ->map(function (Collection $characterEntries) use ($tierRank, $positionOffsets, $maxRank) {
                // The tier bucket is decided purely by the tiers voters
                // actually chose: if every tier list agrees, the median
                // equals that tier exactly, so a character only moves when
                // different tier lists disagree about it.
                $tierVotes = $characterEntries
                    ->map(fn ($entry) => $tierRank[$entry->tier] ?? null)
                    ->filter(fn ($rank) => $rank !== null)
                    ->sort()
                    ->values();

                if ($tierVotes->isEmpty()) {
                    return null;
                }

                $medianRank = min((int) round($this->median($tierVotes)), $maxRank);

                // Within the tier a character was bucketed into, its display
                // order is still driven by within-tier drag position.
                $sortRanks = $characterEntries
                    ->map(function ($entry) use ($tierRank, $positionOffsets) {
                        $rank = $tierRank[$entry->tier] ?? null;

                        return $rank === null ? null : $rank + ($positionOffsets[$entry->idtier_list_entry] ?? 0.0);
                    })
                    ->filter(fn ($rank) => $rank !== null)
                    ->sort()
                    ->values();

                return [
                    'character' => $characterEntries->first()->character,
                    'resourceValue' => $characterEntries->first()->resourceValue,
                    'tier' => TierListEntry::TIERS[$medianRank],
                    'votes' => $tierVotes->count(),
                    'medianPosition' => $this->median($sortRanks),
                ];
            })
            ->filter()
            ->values();

        $tiers = collect(TierListEntry::TIERS)->mapWithKeys(fn ($tier) => [
            $tier => $characters->where('tier', $tier)
                ->sortBy(fn ($entry) => [
                    $entry['medianPosition'],
                    $entry['character']->name,
                    (int) ($entry['resourceValue']->order ?? 0),
                ])
                ->values(),
        ]);

        return [
            'tiers' => $tiers,
            'tierListCount' => $tierListCount,
        ];
    }

    private function median(Collection $sortedValues): float
    {
        $count = $sortedValues->count();
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $sortedValues[$middle]
            : ($sortedValues[$middle - 1] + $sortedValues[$middle]) / 2;
    }
}
