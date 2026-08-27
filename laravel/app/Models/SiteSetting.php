<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row table of site-wide switches, following the only settings pattern
 * this codebase has: DonationProgress::current().
 *
 * Read on nearly every request (the login page and the account pages both ask
 * whether Discord is enabled), so current() memoises for the life of the
 * request. Deliberately not cached in the cache store — there is no
 * invalidation convention here to copy, and a stale feature flag is worse than
 * one extra indexed single-row read.
 */
class SiteSetting extends Model
{
    protected $table = 'site_setting';

    protected $primaryKey = 'idsetting';

    protected $fillable = ['discord_integration_enabled'];

    private static ?self $memo = null;

    protected function casts(): array
    {
        return [
            'discord_integration_enabled' => 'boolean',
        ];
    }

    /**
     * firstOrCreate([]) is "take row 1, or make it" — the same self-seeding
     * singleton accessor DonationProgress uses, so no seeder is required.
     */
    public static function current(): self
    {
        return self::$memo ??= self::query()->firstOrCreate([], [
            'discord_integration_enabled' => true,
        ]);
    }

    public static function discordIntegrationEnabled(): bool
    {
        return self::current()->discord_integration_enabled;
    }

    /**
     * Drops the per-request memo. Called after an admin saves, and by tests
     * that toggle the flag between requests within one process.
     */
    public static function forgetCurrent(): void
    {
        self::$memo = null;
    }
}
