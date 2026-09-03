<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\CombleDailyPick;
use App\Support\DailyGameClock;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

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
     * Picks the same combo for every player on a given date. The pick is
     * persisted the first time a date is requested (comble_daily_picks), so
     * it's locked in forever from that point on — immune to combos/
     * characters added later, and even to future changes to the picking
     * algorithm itself, neither of which a re-derived value could ever be
     * safe from.
     */
    public function forDate(CarbonInterface $date): Combo
    {
        $pick = CombleDailyPick::where('day', $date->toDateString())->first();

        if ($pick === null) {
            $pick = $this->computeAndPersist($date);
        }

        return Combo::with(['character.game', 'listingType'])->findOrFail($pick->combo_idcombo);
    }

    /**
     * Computes the pick for a date that has never been requested before, and
     * persists it. Only reachable the first time a given date is served (see
     * forDate()), so races between concurrent first requests for the same
     * date are handled by racing on the table's unique `day` index: whichever
     * request's create() lands first wins, the other catches the unique
     * violation and re-reads the now-existing row, so both return the same
     * combo.
     */
    private function computeAndPersist(CarbonInterface $date): CombleDailyPick
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

        try {
            return CombleDailyPick::create(['day' => $date->toDateString(), 'combo_idcombo' => $ids[$index]]);
        } catch (QueryException) {
            return CombleDailyPick::where('day', $date->toDateString())->firstOrFail();
        }
    }
}
