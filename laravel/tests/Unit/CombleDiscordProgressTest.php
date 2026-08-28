<?php

namespace Tests\Unit;

use App\Services\CombleDiscordProgress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CombleDiscordProgressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_picks_start_empty(): void
    {
        $progress = new CombleDiscordProgress;

        $this->assertSame([], $progress->picks('111', Carbon::parse('2026-08-20')));
    }

    public function test_append_pick_returns_and_persists_the_updated_list(): void
    {
        $progress = new CombleDiscordProgress;
        $day = Carbon::parse('2026-08-20');

        $returned = $progress->appendPick('111', $day, [1, 2, 3, 100.0, null]);

        $this->assertSame([[1, 2, 3, 100.0, null]], $returned);
        $this->assertSame([[1, 2, 3, 100.0, null]], $progress->picks('111', $day));
    }

    public function test_picks_are_capped_at_five(): void
    {
        $progress = new CombleDiscordProgress;
        $day = Carbon::parse('2026-08-20');

        for ($i = 0; $i < 6; $i++) {
            $progress->appendPick('111', $day, [$i, $i, $i, (float) $i, null]);
        }

        $this->assertCount(5, $progress->picks('111', $day));
    }

    /** Distinct users and distinct days must never see each other's picks. */
    public function test_picks_are_isolated_by_user_and_day(): void
    {
        $progress = new CombleDiscordProgress;
        $dayOne = Carbon::parse('2026-08-20');
        $dayTwo = Carbon::parse('2026-08-21');

        $progress->appendPick('111', $dayOne, [1, 1, 1, 1.0, null]);

        $this->assertSame([], $progress->picks('222', $dayOne));
        $this->assertSame([], $progress->picks('111', $dayTwo));
    }

    /** The "discord:" prefix is what keeps this key space distinct from web session ids in comble_attempts.visitor_key. */
    public function test_visitor_key_is_prefixed_with_discord(): void
    {
        $progress = new CombleDiscordProgress;

        $this->assertSame('discord:12345', $progress->visitorKey('12345'));
    }
}
