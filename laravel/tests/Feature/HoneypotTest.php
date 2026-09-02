<?php

namespace Tests\Feature;

use App\Models\BotHit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoneypotTest extends TestCase
{
    use RefreshDatabase;

    public function test_hitting_the_honeypot_records_a_bot_hit_and_returns_no_content(): void
    {
        $response = $this->withHeaders(['User-Agent' => 'EvilScraper/1.0'])
            ->get(route('honeypot.hit', ['from' => '/games/1']));

        $response->assertNoContent();

        $this->assertDatabaseHas('bot_hits', [
            'path' => '/games/1',
            'user_agent' => 'EvilScraper/1.0',
        ]);
    }

    public function test_hitting_the_honeypot_without_a_from_param_still_records_a_hit(): void
    {
        $this->get(route('honeypot.hit'))->assertNoContent();

        $this->assertSame(1, BotHit::count());
    }
}
