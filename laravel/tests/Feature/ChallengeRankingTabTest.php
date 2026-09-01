<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\User;
use App\Services\DailyChallenge;
use App\Support\DailyGameClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChallengeRankingTabTest extends TestCase
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

    public function test_ranks_a_user_by_how_many_days_their_combo_was_the_top_pick(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 0]);

        // Only one (query, character) pair exists across the whole history, so
        // it's picked deterministically every day, letting the test predict
        // the win count without replicating DailyChallenge's hash formula.
        $query = CharacterQuery::create(['game_idgame' => $game->idgame, 'label' => 'Any starter', 'filters' => [], 'order' => 0]);
        $query->forceFill(['created_at' => Carbon::parse('2026-08-01 00:00:00')])->save();

        $user = User::create(['nickname' => 'Alice', 'password' => 'secret']);

        Combo::create([
            'combo' => 'AAA BBB', 'character_idcharacter' => $character->idcharacter,
            'submited' => now(), 'damage' => 1000, 'type' => $type->entryid, 'user_iduser' => $user->iduser, 'verified' => 1,
        ]);

        $earliestDay = app(DailyChallenge::class)->earliestDate();
        $expectedWins = $earliestDay->diffInDays(DailyGameClock::today()) + 1;

        $response = $this->get(route('challenge.tabs.ranking'));

        $response->assertOk();
        $response->assertSee('Alice');
        $response->assertSee((string) $expectedWins);
    }

    public function test_guest_submitted_combos_are_excluded_from_the_ranking(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 0]);

        $query = CharacterQuery::create(['game_idgame' => $game->idgame, 'label' => 'Any starter', 'filters' => [], 'order' => 0]);
        $query->forceFill(['created_at' => Carbon::parse('2026-08-01 00:00:00')])->save();

        Combo::create([
            'combo' => 'AAA BBB', 'character_idcharacter' => $character->idcharacter,
            'submited' => now(), 'damage' => 1000, 'type' => $type->entryid, 'author' => 'A Guest',
        ]);

        $response = $this->get(route('challenge.tabs.ranking'));

        $response->assertOk();
        $response->assertSee('No ranked combos yet');
        $response->assertDontSee('A Guest');
    }

    public function test_shows_empty_state_when_no_queries_are_configured(): void
    {
        $response = $this->get(route('challenge.tabs.ranking'));

        $response->assertOk();
        $response->assertSee('No ranked combos yet');
    }
}
