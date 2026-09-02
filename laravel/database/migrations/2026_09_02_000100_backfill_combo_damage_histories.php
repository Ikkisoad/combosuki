<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Combo::saved() hook that maintains combo_damage_histories only
     * started running once that table existed, so every pre-existing combo
     * has zero rows there — nothing to show as "history" yet, even for a
     * combo whose damage was already updated for a patch before this
     * feature shipped. This can't recover a value that was overwritten
     * before today (it was never recorded anywhere), but it does seed a
     * baseline row from each combo's current damage/patch so any edit from
     * now on has something to compare against.
     */
    public function up(): void
    {
        $now = now();

        DB::table('combo')
            ->whereNotNull('damage')
            ->whereNotNull('patch_idgame_patch')
            ->orderBy('idcombo')
            ->select(['idcombo', 'patch_idgame_patch', 'damage'])
            ->chunkById(500, function ($combos) use ($now) {
                DB::table('combo_damage_histories')->insertOrIgnore(
                    $combos->map(fn ($combo) => [
                        'combo_idcombo' => $combo->idcombo,
                        'patch_idgame_patch' => $combo->patch_idgame_patch,
                        'damage' => $combo->damage,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            }, 'idcombo', 'idcombo');
    }

    public function down(): void
    {
        // Data migration — not meaningfully reversible. Rolling back the
        // schema migration that created combo_damage_histories removes this
        // data along with it.
    }
};
