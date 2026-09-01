<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\CharacterResourceValueAlias;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\ResourceValue;
use App\Services\DailyChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_home_page_shows_the_top_combo_for_the_days_challenge(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $query = $this->makeQuery($game, 'Random Assist 1');
        $type = $this->makeType($game);
        $this->makeCombo($character, $type, ['combo' => 'AAA weaker combo', 'damage' => 1000]);
        $bestCombo = $this->makeCombo($character, $type, ['combo' => 'BBB strongest combo', 'damage' => 5000]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Challenge')
            ->assertSee($game->name)
            ->assertSee($character->name)
            ->assertSee($query->label)
            ->assertSee($bestCombo->combo)
            ->assertDontSee('weaker combo');
    }

    public function test_the_daily_pick_is_stable_across_repeated_requests(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $query = $this->makeQuery($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $first = app(DailyChallenge::class)->today();
        $second = app(DailyChallenge::class)->today();

        $this->assertSame($query->idquery, $first['query']->idquery);
        $this->assertSame($first['query']->idquery, $second['query']->idquery);
        $this->assertSame($first['character']->idcharacter, $second['character']->idcharacter);
    }

    public function test_incomplete_games_are_excluded_from_the_challenge_pool(): void
    {
        $eligibleGame = $this->makeGame();
        $eligibleCharacter = $this->makeCharacter($eligibleGame);
        $eligibleQuery = $this->makeQuery($eligibleGame);

        $incompleteGame = $this->makeGame(['complete' => 0]);
        $incompleteCharacter = $this->makeCharacter($incompleteGame, 'Ken');
        $this->makeQuery($incompleteGame);

        $challenge = app(DailyChallenge::class)->today();

        $this->assertSame($eligibleQuery->idquery, $challenge['query']->idquery);
        $this->assertSame($eligibleCharacter->idcharacter, $challenge['character']->idcharacter);
    }

    public function test_a_query_created_today_is_excluded_from_todays_pick(): void
    {
        $game = $this->makeGame();
        $this->makeCharacter($game);
        $establishedQuery = $this->makeQuery($game, 'Established Query');

        $before = app(DailyChallenge::class)->today();

        // Created "now" — i.e. on the same day as the challenge being served.
        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => 'Brand New Query',
            'filters' => [],
            'order' => 0,
        ]);

        $after = app(DailyChallenge::class)->today();

        $this->assertSame($establishedQuery->idquery, $before['query']->idquery);
        $this->assertSame($establishedQuery->idquery, $after['query']->idquery);
    }

    public function test_the_home_page_still_works_when_no_queries_are_configured(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('No challenge is available yet');
    }

    public function test_a_query_with_no_matching_combo_invites_the_first_submission(): void
    {
        $game = $this->makeGame();
        $this->makeCharacter($game);
        $this->makeQuery($game);

        $this->get('/')
            ->assertOk()
            ->assertSee('be the first to submit one');
    }

    public function test_the_querys_actual_filter_criteria_are_spelled_out(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $this->makeQuery($game, '2LK starter, no meter', [
            'combo' => '2LK',
            'combolike' => 0,
            'damage' => '1000',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Character: '.$character->name)
            ->assertSee('Starts with &quot;2LK&quot;', false)
            ->assertSee('Damage ≤ 1000', false);
    }

    public function test_the_challenge_criteria_use_the_characters_resource_value_alias(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);

        $support = GameResource::create([
            'game_idgame' => $game->idgame,
            'text_name' => 'Support',
            'type' => 1,
            'primaryORsecundary' => 1,
        ]);
        $value = ResourceValue::create(['value' => '3', 'game_resources_idgame_resources' => $support->idgame_resources]);

        CharacterResourceValueAlias::create([
            'alias' => 'Doggy Assist',
            'character_idcharacter' => $character->idcharacter,
            'resources_values_idResources_values' => $value->idResources_values,
        ]);

        $this->makeQuery($game, 'Support 3 combos', [
            'Support' => $value->idResources_values,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Support: Doggy Assist')
            ->assertDontSee('Support: 3');
    }

    public function test_results_between_matches_for_date_pick_by_pick(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $this->makeQuery($game, 'Established Query');
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        // Created mid-range so the range straddles the eligible/not-yet-eligible
        // boundary, exercising both branches of pickPair() across the batch.
        CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => 'Newer Query',
            'filters' => [],
            'order' => 1,
        ])->forceFill(['created_at' => Carbon::parse('2026-08-15 00:00:00')])->save();

        $from = Carbon::parse('2026-08-10 00:00:00', 'America/Sao_Paulo');
        $to = Carbon::parse('2026-08-20 00:00:00', 'America/Sao_Paulo');

        $results = app(DailyChallenge::class)->resultsBetween($from, $to);

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $expected = app(DailyChallenge::class)->forDate($day);
            $actual = $results[$day->toDateString()];

            $this->assertSame($expected['query']?->idquery, $actual['query']?->idquery, "query mismatch on {$day->toDateString()}");
            $this->assertSame($expected['character']?->idcharacter, $actual['character']?->idcharacter, "character mismatch on {$day->toDateString()}");
            $this->assertSame($expected['combo']?->idcombo, $actual['combo']?->idcombo, "combo mismatch on {$day->toDateString()}");
        }
    }

    public function test_results_between_returns_null_fields_for_dates_before_any_query_existed(): void
    {
        $game = $this->makeGame();
        $this->makeCharacter($game);
        $this->makeQuery($game);

        $results = app(DailyChallenge::class)->resultsBetween(
            Carbon::parse('2026-07-01 00:00:00', 'America/Sao_Paulo'),
            Carbon::parse('2026-07-02 00:00:00', 'America/Sao_Paulo'),
        );

        $this->assertNull($results['2026-07-01']['query']);
        $this->assertNull($results['2026-07-01']['combo']);
        $this->assertNull($results['2026-07-02']['query']);
    }

    public function test_results_between_does_not_requery_the_database_per_day(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $this->makeQuery($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        DB::enableQueryLog();
        app(DailyChallenge::class)->resultsBetween(
            Carbon::parse('2026-08-10 00:00:00', 'America/Sao_Paulo'),
            Carbon::parse('2026-08-11 00:00:00', 'America/Sao_Paulo'),
        );
        $shortRangeQueries = count(DB::getQueryLog());
        DB::flushQueryLog();

        app(DailyChallenge::class)->resultsBetween(
            Carbon::parse('2026-08-10 00:00:00', 'America/Sao_Paulo'),
            Carbon::parse('2026-09-08 00:00:00', 'America/Sao_Paulo'),
        );
        $longRangeQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Same single (query, character) pair is picked every day in both
        // ranges, so query count shouldn't grow with the number of days.
        $this->assertSame($shortRangeQueries, $longRangeQueries);
    }

    private function makeGame(array $overrides = []): Game
    {
        return Game::create(array_merge([
            'name' => 'Test Fighter '.uniqid(),
            'complete' => 1,
            'modPass' => 'x',
        ], $overrides));
    }

    private function makeCharacter(Game $game, string $name = 'Ryu'): Character
    {
        return Character::create(['name' => $name, 'game_idgame' => $game->idgame]);
    }

    private function makeQuery(Game $game, string $label = 'Random Assist 1', array $filters = []): CharacterQuery
    {
        $query = CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => $label,
            'filters' => $filters,
            'order' => 0,
        ]);

        // Backdated so it's already eligible for the fixed "today" used throughout
        // these tests — see test_a_query_created_today_is_excluded_from_todays_pick
        // for fixtures that need to represent a query created "just now" instead.
        $query->forceFill(['created_at' => Carbon::parse('2026-08-01 00:00:00')])->save();

        return $query;
    }

    private function makeType(Game $game, string $title = 'Combo'): GameEntry
    {
        return GameEntry::create(['title' => $title, 'gameid' => $game->idgame, 'order' => 0]);
    }

    private function makeCombo(Character $character, GameEntry $type, array $overrides = []): Combo
    {
        return Combo::create(array_merge([
            'combo' => 'AAA BBB CCC',
            'character_idcharacter' => $character->idcharacter,
            'submited' => now(),
            'type' => $type->entryid,
            'damage' => 3000,
        ], $overrides));
    }
}
