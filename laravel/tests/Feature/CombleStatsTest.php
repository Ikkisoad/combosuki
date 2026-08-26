<?php

namespace Tests\Feature;

use App\Models\CombleAttempt;
use App\Services\CombleStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CombleStatsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CombleStats used to aggregate every attempt ever recorded, so a busy
     * day would drown out today's numbers. summary() now takes the day to
     * report on and must ignore rows from every other day.
     */
    public function test_summary_only_counts_attempts_from_the_requested_day(): void
    {
        CombleAttempt::create(['day' => '2026-08-19', 'visitor_key' => 'a', 'guesses' => 2, 'won' => true, 'perfect' => false]);
        CombleAttempt::create(['day' => '2026-08-19', 'visitor_key' => 'b', 'guesses' => 5, 'won' => false, 'perfect' => false]);
        CombleAttempt::create(['day' => '2026-08-18', 'visitor_key' => 'c', 'guesses' => 1, 'won' => true, 'perfect' => true]);

        $summary = app(CombleStats::class)->summary(Carbon::parse('2026-08-19'));

        $this->assertSame(2, $summary['totalAttempts']);
        $this->assertSame(1, $summary['totalWins']);
        $this->assertSame(50.0, $summary['winRate']);
        $this->assertSame(0, $summary['totalPerfect'], 'the perfect score from 08-18 must not leak into 08-19\'s count');
        $this->assertSame(1, $summary['distribution'][2]);
        $this->assertSame(1, $summary['distribution']['lost']);
        $this->assertSame(0, $summary['distribution'][1]);
    }

    public function test_summary_for_a_day_with_no_attempts_is_all_zero(): void
    {
        CombleAttempt::create(['day' => '2026-08-19', 'visitor_key' => 'a', 'guesses' => 1, 'won' => true, 'perfect' => true]);

        $summary = app(CombleStats::class)->summary(Carbon::parse('2026-08-20'));

        $this->assertSame(0, $summary['totalAttempts']);
        $this->assertSame(0, $summary['totalWins']);
        $this->assertSame(0.0, $summary['winRate']);
        $this->assertSame(0, $summary['totalPerfect']);
    }
}
