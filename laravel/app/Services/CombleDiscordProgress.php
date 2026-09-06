<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * In-progress Comble picks and the finished-attempt visitor-key format for
 * any Discord-identity-keyed surface — the bot (DiscordCombleGame) and the
 * Discord Activity (ActivityCombleController). Neither surface has a
 * Laravel session/cookie available (bot interactions carry none; the
 * Activity's iframe is proxied through discordsays.com and isn't relied on
 * to carry one either), so progress is kept server-side in cache, keyed by
 * Discord user id + date, and shared here so a player's progress is the
 * same whichever surface they play from.
 */
class CombleDiscordProgress
{
    private const MAX_GUESSES = 5;

    /** Generous relative to the puzzle's 1-day relevance window, just to bound cache growth. */
    private const CACHE_TTL_DAYS = 3;

    /** Comfortably longer than ActivityAuthController's 2-hour token TTL, since a play session can't outlive that token anyway. */
    private const CHANNEL_CACHE_TTL_HOURS = 3;

    public function picks(string $userId, Carbon $day): array
    {
        return Cache::get($this->cacheKey($userId, $day), []);
    }

    /** Appends one raw pick and returns the resulting (already-capped) picks list, saving a round-trip for callers that need it right after. */
    public function appendPick(string $userId, Carbon $day, array $pick): array
    {
        $picks = array_slice([...$this->picks($userId, $day), $pick], 0, self::MAX_GUESSES);

        Cache::put($this->cacheKey($userId, $day), $picks, now()->addDays(self::CACHE_TTL_DAYS));

        return $picks;
    }

    /**
     * Discord user ids are stable, globally unique identities (unlike the
     * web flow's rotating session id), so this alone is enough to dedup one
     * CombleAttempt row per Discord player per day — the "discord:" prefix
     * keeps this key space distinct from web session ids, and is shared by
     * every Discord-identity surface so the same player playing from either
     * one hits the same attempt row.
     */
    public function visitorKey(string $userId): string
    {
        return 'discord:'.$userId;
    }

    /**
     * Remembers which text channel `/csk comble` was invoked from, so
     * ActivityCombleController can announce a finished puzzle back into it
     * once the player completes the game through the Activity — the
     * Activity's own token exchange (ActivityAuthController) never learns
     * the channel it's framed in, only the player's identity, so the
     * command interaction (which does carry channel_id) is the only place
     * this can be captured.
     */
    public function rememberChannel(string $userId, string $channelId): void
    {
        Cache::put($this->channelCacheKey($userId), $channelId, now()->addHours(self::CHANNEL_CACHE_TTL_HOURS));
    }

    public function channelFor(string $userId): ?string
    {
        return Cache::get($this->channelCacheKey($userId));
    }

    private function cacheKey(string $userId, Carbon $day): string
    {
        return 'comble:discord:'.$userId.':'.$day->toDateString();
    }

    private function channelCacheKey(string $userId): string
    {
        return 'comble:discord:channel:'.$userId;
    }
}
