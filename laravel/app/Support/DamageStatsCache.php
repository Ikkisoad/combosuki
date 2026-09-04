<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * GameController::damageStatsTab() aggregates every CharacterQuery against
 * every Character for a game (see FiltersCombos::searchCombos()), which is
 * expensive enough to be worth caching rather than recomputing on every tab
 * view. The result only actually changes when a Combo belonging to the game
 * is created, updated, or deleted, so it's cached forever and invalidated
 * from those three spots (see Combo::booted()) instead of on a time-based
 * expiry.
 *
 * Kept as two separate entries per game — one for trusted viewers, one for
 * everyone else — because the underlying search is visibility-scoped (see
 * FiltersCombos::searchCombos()'s $trustedOverride and Combo::scopeVisibleTo()):
 * a trusted staff member sees every combo, including unverified ones, while
 * a guest/regular visitor only sees verified (or otherwise vouched-for)
 * ones. A single shared cache entry would bake in whichever tier happened to
 * trigger the last recompute for every other visitor, regardless of their
 * own tier.
 */
class DamageStatsCache
{
    public static function key(int $gameId, bool $trusted): string
    {
        $tier = $trusted ? 'trusted' : 'public';

        return "games.{$gameId}.damage-stats.{$tier}";
    }

    public static function forget(int $gameId): void
    {
        Cache::forget(self::key($gameId, true));
        Cache::forget(self::key($gameId, false));
    }
}
