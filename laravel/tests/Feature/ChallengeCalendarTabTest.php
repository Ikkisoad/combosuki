<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Services\DailyChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChallengeCalendarTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-25 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_year_straddling_the_earliest_day_only_includes_in_range_days(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);

        $query = CharacterQuery::create(['game_idgame' => $game->idgame, 'label' => 'Any starter', 'filters' => [], 'order' => 0]);
        $query->forceFill(['created_at' => Carbon::parse('2026-08-10 00:00:00')])->save();

        $earliestDay = app(DailyChallenge::class)->earliestDate();

        $response = $this->getJson(route('challenge.tabs.calendar', ['year' => 2026]));

        $response->assertOk();
        $days = $response->json('days');

        $this->assertArrayNotHasKey($earliestDay->copy()->subDay()->toDateString(), $days);
        $this->assertArrayHasKey($earliestDay->toDateString(), $days);
        $this->assertArrayHasKey('2026-08-25', $days);
        $this->assertArrayNotHasKey('2026-08-26', $days);
    }

    public function test_a_day_with_no_matching_combo_is_open(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);

        $query = CharacterQuery::create(['game_idgame' => $game->idgame, 'label' => 'Any starter', 'filters' => [], 'order' => 0]);
        $query->forceFill(['created_at' => Carbon::parse('2026-08-01 00:00:00')])->save();

        $response = $this->getJson(route('challenge.tabs.calendar', ['year' => 2026]));

        $response->assertOk();
        $response->assertJsonPath('days.2026-08-25', 'open');
    }

    public function test_a_day_with_a_matching_combo_is_solved(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 0]);

        $query = CharacterQuery::create(['game_idgame' => $game->idgame, 'label' => 'Any starter', 'filters' => [], 'order' => 0]);
        $query->forceFill(['created_at' => Carbon::parse('2026-08-01 00:00:00')])->save();

        Combo::create([
            'combo' => 'AAA BBB', 'character_idcharacter' => $character->idcharacter,
            'submited' => now(), 'damage' => 1000, 'type' => $type->entryid,
        ]);

        $response = $this->getJson(route('challenge.tabs.calendar', ['year' => 2026]));

        $response->assertOk();
        $response->assertJsonPath('days.2026-08-25', 'solved');
    }

    public function test_a_year_entirely_before_the_earliest_day_returns_no_days(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);

        $query = CharacterQuery::create(['game_idgame' => $game->idgame, 'label' => 'Any starter', 'filters' => [], 'order' => 0]);
        $query->forceFill(['created_at' => Carbon::parse('2026-08-10 00:00:00')])->save();

        $earliestDay = app(DailyChallenge::class)->earliestDate();

        $response = $this->getJson(route('challenge.tabs.calendar', ['year' => 2025]));

        $response->assertOk();
        $response->assertExactJson(['days' => [], 'earliest' => $earliestDay->toDateString(), 'today' => '2026-08-25']);
    }

    public function test_returns_no_days_when_no_queries_are_configured(): void
    {
        $response = $this->getJson(route('challenge.tabs.calendar', ['year' => 2026]));

        $response->assertOk();
        $response->assertExactJson(['days' => [], 'earliest' => null, 'today' => '2026-08-25']);
    }
}
