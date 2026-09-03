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
        // first) is folded into its rank as a fractional offset in [0, 1), so
        // two votes for the same tier aren't treated as identical: one placed
        // near the top of the tier ranks slightly better than one placed near
        // the bottom.
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
                $ranks = $characterEntries
                    ->map(function ($entry) use ($tierRank, $positionOffsets) {
                        $rank = $tierRank[$entry->tier] ?? null;

                        return $rank === null ? null : $rank + ($positionOffsets[$entry->idtier_list_entry] ?? 0.0);
                    })
                    ->filter(fn ($rank) => $rank !== null)
                    ->sort()
                    ->values();

                if ($ranks->isEmpty()) {
                    return null;
                }

                $count = $ranks->count();
                $middle = intdiv($count, 2);
                $median = $count % 2 === 1
                    ? $ranks[$middle]
                    : ($ranks[$middle - 1] + $ranks[$middle]) / 2;

                $medianRank = min((int) round($median), $maxRank);

                return [
                    'character' => $characterEntries->first()->character,
                    'resourceValue' => $characterEntries->first()->resourceValue,
                    'tier' => TierListEntry::TIERS[$medianRank],
                    'votes' => $count,
                    'medianPosition' => $median,
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
}
