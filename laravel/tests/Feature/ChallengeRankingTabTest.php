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

    /**
     * The ranking is cached forever per day (see ChallengeStatsCache) since
     * recomputing it re-derives the whole challenge history — this verifies
     * a newly submitted winning combo isn't hidden behind a stale cache
     * entry until the version Combo::booted() bumps takes effect.
     */
    public function test_cached_ranking_is_invalidated_when_a_new_winning_combo_is_added(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 0]);

        $query = CharacterQuery::create(['game_idgame' => $game->idgame, 'label' => 'Any starter', 'filters' => [], 'order' => 0]);
        $query->forceFill(['created_at' => Carbon::parse('2026-08-01 00:00:00')])->save();

        // Primes the cache with no ranked combos yet.
        $this->get(route('challenge.tabs.ranking'))->assertSee('No ranked combos yet');

        $user = User::create(['nickname' => 'Alice', 'password' => 'secret']);
        Combo::create([
            'combo' => 'AAA BBB', 'character_idcharacter' => $character->idcharacter,
            'submited' => now(), 'damage' => 1000, 'type' => $type->entryid, 'user_iduser' => $user->iduser, 'verified' => 1,
        ]);

        $this->get(route('challenge.tabs.ranking'))->assertSee('Alice');
    }

    /**
     * Regression test: DailyChallenge::resultsBetween() (which the ranking
     * cache wraps) scopes its combo search by visibility, same as the
     * damage-stats tab — a trusted viewer sees every combo, a guest only
     * sees verified (or otherwise vouched-for) ones. The cache key has to
     * carry that tier (see ChallengeStatsCache::rankingKey()'s $trusted
     * segment) or a guest caching the tab first hides an unverified winning
     * combo from a trusted visitor who should be able to see it.
     */
    public function test_a_trusted_viewer_sees_an_unverified_win_even_if_a_guest_cached_the_tab_first(): void
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
        $character = Character::create(['name' => 'Ryu', 'game_idgame' => $game->idgame]);
        $type = GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => 0]);

        $query = CharacterQuery::create(['game_idgame' => $game->idgame, 'label' => 'Any starter', 'filters' => [], 'order' => 0]);
        $query->forceFill(['created_at' => Carbon::parse('2026-08-01 00:00:00')])->save();

        $regularUser = User::create(['nickname' => 'Alice', 'password' => 'secret']);
        Combo::create([
            'combo' => 'AAA BBB', 'character_idcharacter' => $character->idcharacter, 'submited' => now(),
            'damage' => 1000, 'type' => $type->entryid, 'user_iduser' => $regularUser->iduser, 'verified' => 0,
        ]);

        // A guest views the tab first, priming the "public" bucket — Alice's
        // unverified combo doesn't count there.
        $this->get(route('challenge.tabs.ranking'))->assertSee('No ranked combos yet');

        // A trusted staff member must still see it counted for Alice,
        // served from a separate "trusted" bucket.
        $trusted = User::create(['nickname' => 'Admin', 'password' => 'secret', 'trusted_user' => true]);
        $this->actingAs($trusted)
            ->get(route('challenge.tabs.ranking'))
            ->assertSee('Alice');
    }
}
