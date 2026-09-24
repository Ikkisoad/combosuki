<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChallengeArchiveTest extends TestCase
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

    public function test_the_home_page_links_to_the_challenge_archive(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('challenge.show'), false);
    }

    public function test_the_challenge_page_shows_todays_challenge(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $query = $this->makeQuery($game, 'Random Assist 1');
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $this->get(route('challenge.show'))
            ->assertOk()
            ->assertSee("Today's challenge")
            ->assertSee($character->name)
            ->assertSee($query->label);
    }

    public function test_the_challenge_page_shows_a_past_days_challenge(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $query = $this->makeQuery($game, 'Random Assist 1');
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $day = Carbon::parse('2026-08-10');

        $this->get(route('challenge.show.date', ['date' => $day->toDateString()]))
            ->assertOk()
            ->assertSee($day->format('F j, Y'))
            ->assertSee($character->name)
            ->assertSee($query->label);
    }

    public function test_the_next_day_link_is_hidden_on_todays_page_but_shown_on_a_past_page(): void
    {
        $this->get(route('challenge.show'))
            ->assertOk()
            ->assertDontSee(route('challenge.show.date', ['date' => '2026-08-20']), false);

        $this->get(route('challenge.show.date', ['date' => '2026-08-18']))
            ->assertOk()
            ->assertSee(route('challenge.show.date', ['date' => '2026-08-19']), false);
    }

    public function test_the_previous_day_link_is_hidden_on_the_earliest_available_challenge_day(): void
    {
        $game = $this->makeGame();
        $this->makeCharacter($game);
        $this->makeQuery($game);

        // makeQuery backdates created_at to 2026-08-01 00:00:00 UTC, which is
        // still 2026-07-31 in the challenge's America/Sao_Paulo clock, so the
        // pool first becomes non-empty on 2026-08-01.
        $this->get(route('challenge.show.date', ['date' => '2026-08-01']))
            ->assertOk()
            ->assertDontSee(route('challenge.show.date', ['date' => '2026-07-31']), false);

        $this->get(route('challenge.show.date', ['date' => '2026-08-02']))
            ->assertOk()
            ->assertSee(route('challenge.show.date', ['date' => '2026-08-01']), false);
    }

    public function test_the_date_pickers_minimum_matches_the_earliest_available_challenge_day(): void
    {
        $game = $this->makeGame();
        $this->makeCharacter($game);
        $this->makeQuery($game);

        $this->get(route('challenge.show'))
            ->assertOk()
            ->assertSee('min="2026-08-01"', false);
    }

    public function test_a_future_date_404s(): void
    {
        $this->get(route('challenge.show.date', ['date' => '2026-08-20']))
            ->assertNotFound();
    }

    public function test_an_invalid_calendar_date_404s(): void
    {
        $this->get('/challenge/2026-02-30')
            ->assertNotFound();
    }

    public function test_the_day_panel_returns_just_that_days_section(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $query = $this->makeQuery($game, 'Random Assist 1');
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $this->get(route('challenge.day.panel', ['date' => '2026-08-10']))
            ->assertOk()
            ->assertSee('August 10, 2026')
            ->assertSee($character->name)
            ->assertSee($query->label)
            ->assertSee('data-date="2026-08-10"', false)
            ->assertSee('data-title="Challenge — Aug 10, 2026 - Combo好き"', false)
            ->assertSee('data-challenge-day="2026-08-09"', false)
            ->assertSee('data-challenge-day="2026-08-11"', false)
            // Only the day section — not the surrounding page chrome/tabs.
            ->assertDontSee('challenge-tabs', false)
            ->assertDontSee('<html', false);
    }

    public function test_the_day_panel_for_today_has_no_next_day_link(): void
    {
        $this->get(route('challenge.day.panel', ['date' => '2026-08-19']))
            ->assertOk()
            ->assertSee("Today's challenge")
            ->assertDontSee('data-challenge-day="2026-08-20"', false);
    }

    public function test_the_challenge_page_embeds_the_day_panel_for_in_place_navigation(): void
    {
        $this->get(route('challenge.show.date', ['date' => '2026-08-18']))
            ->assertOk()
            ->assertSee('id="challenge-day"', false)
            ->assertSee('data-date="2026-08-18"', false)
            ->assertSee('data-challenge-day="2026-08-19"', false);
    }

    public function test_the_day_panel_404s_for_future_and_invalid_dates(): void
    {
        $this->get(route('challenge.day.panel', ['date' => '2026-08-20']))->assertNotFound();
        $this->get('/challenge/2026-02-30/panel')->assertNotFound();
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
