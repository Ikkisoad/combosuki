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

    private function makeQuery(Game $game, string $label = 'Random Assist 1'): CharacterQuery
    {
        return CharacterQuery::create([
            'game_idgame' => $game->idgame,
            'label' => $label,
            'filters' => [],
            'order' => 0,
        ]);
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
