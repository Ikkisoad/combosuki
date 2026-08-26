<?php

namespace App\Services;

use App\Models\Game;
use App\Models\TierListEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TierListAggregator
{
    private const TIER_ORDER = ['S', 'A', 'B', 'C', 'D', 'F'];

    public function aggregate(Game $game, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $tierRank = array_flip(self::TIER_ORDER);

        $entries = TierListEntry::query()
            ->with('character', 'resourceValue')
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

        $characters = $entries->groupBy(fn ($entry) => $entry->character_idcharacter.'|'.($entry->resources_values_idResources_values ?? ''))
            ->map(function (Collection $characterEntries) use ($tierRank) {
                $ranks = $characterEntries
                    ->map(fn ($entry) => $tierRank[$entry->tier] ?? null)
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

                $medianRank = (int) round($median);

                return [
                    'character' => $characterEntries->first()->character,
                    'resourceValue' => $characterEntries->first()->resourceValue,
                    'tier' => self::TIER_ORDER[$medianRank],
                    'votes' => $count,
                ];
            })
            ->filter()
            ->values();

        $tiers = collect(self::TIER_ORDER)->mapWithKeys(fn ($tier) => [
            $tier => $characters->where('tier', $tier)
                ->sortBy(fn ($entry) => $entry['character']->name.'-'.str_pad((string) ($entry['resourceValue']->order ?? 0), 5, '0', STR_PAD_LEFT))
                ->values(),
        ]);

        return [
            'tiers' => $tiers,
            'tierListCount' => $tierListCount,
        ];
    }
}
