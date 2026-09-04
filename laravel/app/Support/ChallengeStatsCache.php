<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * ChallengeController::rankingTab()/calendarTab() both re-derive the whole
 * Daily Challenge history via DailyChallenge::resultsBetween(), which grows
 * more expensive (one FiltersCombos::searchCombos() call per distinct
 * query/character pair ever picked) as the challenge's history grows —
 * worth caching rather than recomputing on every tab view.
 *
 * Unlike the (query, character) pair a day is assigned (which is persisted
 * forever in daily_challenge_picks and never changes), the *winning combo*
 * for that pair is re-searched live every time and can change whenever a
 * new/edited/deleted Combo affects the top result for that pair — so a
 * naive "cache once a day" policy would go stale the moment a relevant combo
 * is written. Instead, every cached entry's key carries a version number
 * that Combo::booted() bumps on every combo write, invalidating every
 * cached ranking/calendar entry at once without needing a tag-capable cache
 * store (this app's cache defaults to the `file` driver on shared hosting,
 * which has no tag support). The day boundary (new "today") is handled by
 * folding today's date into the key instead, so a new day's entry is simply
 * a fresh key rather than something that needs active invalidation.
 *
 * Also split by viewer trust tier for the same reason DamageStatsCache is:
 * the underlying search is visibility-scoped (see
 * FiltersCombos::searchCombos()'s $trustedOverride), so a single shared
 * entry would bake in whichever tier happened to trigger the last recompute
 * for every other visitor regardless of their own tier.
 */
class ChallengeStatsCache
{
    private const VERSION_KEY = 'challenge.cache-version';

    public static function rankingKey(string $todayDateString, bool $trusted): string
    {
        return 'challenge.ranking.'.$todayDateString.'.'.self::tier($trusted).'.v'.self::version();
    }

    public static function calendarKey(int $year, string $todayDateString, bool $trusted): string
    {
        return "challenge.calendar.{$year}.{$todayDateString}.".self::tier($trusted).'.v'.self::version();
    }

    private static function tier(bool $trusted): string
    {
        return $trusted ? 'trusted' : 'public';
    }

    public static function bumpVersion(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    private static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn () => 1);
    }
}
