<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Best-effort backfill: there's no real historical start/end date data
     * for existing patches, only free-text `combo.patch`/`game.patch`
     * strings and combo submission timestamps. This synthesizes a plausible
     * timeline per game by chaining each distinct patch label's earliest
     * combo submission date. Deliberately approximate — see the "Rework
     * patch into a dated patch list" plan for the accepted tradeoffs.
     *
     * Written with the query builder (no joins in UPDATE, which SQLite's
     * grammar can't compile) so it runs identically against the SQLite test
     * suite and production MySQL.
     */
    public function up(): void
    {
        $games = DB::table('game')->get(['idgame', 'patch', 'created_at', 'updated_at']);

        foreach ($games as $game) {
            $characterIds = DB::table('character')->where('game_idgame', $game->idgame)->pluck('idcharacter');

            $labels = collect(
                DB::table('combo')
                    ->whereIn('character_idcharacter', $characterIds)
                    ->whereNotNull('patch')
                    ->where('patch', '!=', '')
                    ->distinct()
                    ->pluck('patch')
            );

            if ($game->patch !== null && trim((string) $game->patch) !== '') {
                $labels->push($game->patch);
            }

            $labels = $labels->unique()->values();

            if ($labels->isEmpty()) {
                continue;
            }

            $fallback = $game->updated_at ?? $game->created_at ?? now();

            $entries = $labels->map(function (string $label) use ($characterIds, $fallback) {
                $earliest = DB::table('combo')
                    ->whereIn('character_idcharacter', $characterIds)
                    ->where('patch', $label)
                    ->min('created_at');

                return [
                    'label' => $label,
                    'released_at' => Carbon::parse($earliest ?? $fallback),
                ];
            })->sortBy(fn (array $entry) => $entry['released_at']->format('Y-m-d').'|'.$entry['label'])->values();

            $now = now();

            foreach ($entries as $index => $entry) {
                $next = $entries->get($index + 1);

                $idGamePatch = DB::table('game_patches')->insertGetId([
                    'game_idgame' => $game->idgame,
                    'label' => $entry['label'],
                    'released_at' => $entry['released_at']->toDateString(),
                    'ended_at' => $next ? $next['released_at']->toDateString() : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('combo')
                    ->whereIn('character_idcharacter', $characterIds)
                    ->where('patch', $entry['label'])
                    ->update(['patch_idgame_patch' => $idGamePatch]);
            }
        }
    }

    public function down(): void
    {
        // Data migration — not meaningfully reversible. Rolling back the
        // schema migration that created game_patches/patch_idgame_patch
        // removes this data along with it.
    }
};
