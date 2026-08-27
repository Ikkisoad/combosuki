<?php

namespace App\Services;

use App\Models\Combo;
use App\Support\DailyGameClock;
use Carbon\CarbonInterface;

class CombleDailyCombo
{
    /**
     * Combos need at least this many space-separated tokens to be eligible,
     * so the 5-guess reveal cadence in CombleRevealer always has something
     * new to show (in the minimal case, one token per guess).
     */
    private const MIN_TOKENS = 5;

    public function today(): Combo
    {
        return $this->forDate(DailyGameClock::today());
    }

    /**
     * Picks the same combo for every player on a given date, without
     * persisting anything: the eligible id list is deterministic (ordered by
     * idcombo) and the index into it is derived from a hash of the date.
     */
    public function forDate(CarbonInterface $date): Combo
    {
        // Pinned to guest-level visibility (null viewer), never the request's
        // actual auth()->user(): every player must see the same puzzle for a
        // given date, so the eligible set can't vary by who's asking.
        $ids = Combo::query()
            ->join('character', 'character.idcharacter', '=', 'combo.character_idcharacter')
            ->join('game', 'game.idgame', '=', 'character.game_idgame')
            ->where('game.complete', '>', 0)
            ->whereRaw("(LENGTH(combo.combo) - LENGTH(REPLACE(combo.combo, ' ', ''))) + 1 >= ?", [self::MIN_TOKENS])
            ->visibleTo(null)
            ->orderBy('combo.idcombo')
            ->pluck('combo.idcombo');

        abort_if($ids->isEmpty(), 404, 'No combos are eligible for a Comble puzzle yet.');

        $seed = hexdec(substr(hash('sha256', $date->toDateString()), 0, 8));
        $index = $seed % $ids->count();

        return Combo::with(['character.game', 'listingType'])->findOrFail($ids[$index]);
    }
}
