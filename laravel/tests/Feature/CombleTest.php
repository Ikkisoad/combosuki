<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Services\CombleDailyCombo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CombleTest extends TestCase
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

    public function test_the_daily_target_is_stable_across_repeated_requests(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $combo = $this->makeCombo($character, $type);

        $this->showPage()->assertOk();
        $first = app(CombleDailyCombo::class)->today();

        $this->showPage()->assertOk();
        $second = app(CombleDailyCombo::class)->today();

        $this->assertSame($combo->idcombo, $first->idcombo);
        $this->assertSame($first->idcombo, $second->idcombo);
    }

    public function test_only_eligible_combos_can_be_selected(): void
    {
        $eligibleGame = $this->makeGame();
        $eligibleCharacter = $this->makeCharacter($eligibleGame);
        $eligibleType = $this->makeType($eligibleGame);
        $eligibleCombo = $this->makeCombo($eligibleCharacter, $eligibleType);

        $this->makeCombo($eligibleCharacter, $eligibleType, ['combo' => 'AAA']);

        $incompleteGame = $this->makeGame(['complete' => 0]);
        $incompleteCharacter = $this->makeCharacter($incompleteGame, 'Ken');
        $incompleteType = $this->makeType($incompleteGame);
        $this->makeCombo($incompleteCharacter, $incompleteType);

        $target = app(CombleDailyCombo::class)->today();

        $this->assertSame($eligibleCombo->idcombo, $target->idcombo);
    }

    public function test_a_correct_guess_wins_and_reveals_the_answer(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $response = $this->submitGuess($this->guessPayload($game, $character, $type));
        $response->assertRedirect(route('comble.show'));

        $this->showPage(cookie: $this->cookieFromResponse($response))
            ->assertOk()
            ->assertSee('You got it!')
            ->assertSee($character->name)
            ->assertSee($game->name)
            ->assertSee('3.000 dmg');
    }

    public function test_the_share_text_summarizes_the_guess_history(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $wrongGame = $this->makeGame(['name' => 'Wrong Game']);
        $wrongCharacter = $this->makeCharacter($wrongGame, 'Vega');
        $wrongType = $this->makeType($wrongGame);

        $first = $this->submitGuess($this->guessPayload($wrongGame, $wrongCharacter, $wrongType));
        $cookie = $this->cookieFromResponse($first);

        $second = $this->submitGuess($this->guessPayload($game, $character, $type), cookie: $cookie);
        $cookie = $this->cookieFromResponse($second);

        $this->showPage(cookie: $cookie)
            ->assertOk()
            ->assertSee('Copy Results')
            ->assertSee('🟥🟥🟥', false)
            ->assertSee('🟩🟩🟩', false)
            ->assertSee('2/5', false)
            ->assertDontSee('X/5', false);
    }

    public function test_a_wrong_guess_keeps_the_puzzle_in_progress(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $otherGame = $this->makeGame(['name' => 'Other Game']);
        $otherCharacter = $this->makeCharacter($otherGame, 'Chun-Li');
        $otherType = $this->makeType($otherGame);

        $response = $this->submitGuess($this->guessPayload($otherGame, $otherCharacter, $otherType));

        $this->showPage(cookie: $this->cookieFromResponse($response))
            ->assertOk()
            ->assertDontSee('You got it!')
            ->assertDontSee('Better luck tomorrow!')
            ->assertSee('4 guesses left');
    }

    public function test_five_wrong_guesses_ends_the_puzzle_as_a_loss(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $wrongGame = $this->makeGame(['name' => 'Wrong Game']);
        $wrongCharacter = $this->makeCharacter($wrongGame, 'Guile');
        $wrongType = $this->makeType($wrongGame);

        $cookie = null;

        for ($i = 0; $i < 5; $i++) {
            $response = $this->submitGuess($this->guessPayload($wrongGame, $wrongCharacter, $wrongType), cookie: $cookie);
            $cookie = $this->cookieFromResponse($response);
        }

        $this->showPage(cookie: $cookie)
            ->assertOk()
            ->assertSee('Better luck tomorrow!')
            ->assertSee($character->name);
    }

    public function test_a_finished_puzzle_rejects_further_guesses(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $winResponse = $this->submitGuess($this->guessPayload($game, $character, $type));
        $cookie = $this->cookieFromResponse($winResponse);

        $secondGame = $this->makeGame(['name' => 'Another Game']);
        $secondCharacter = $this->makeCharacter($secondGame, 'Blanka');
        $secondType = $this->makeType($secondGame);

        $replayResponse = $this->submitGuess($this->guessPayload($secondGame, $secondCharacter, $secondType), cookie: $cookie);

        $replayResponse->assertSessionHas('error');
        $this->assertNull($this->cookieFromResponse($replayResponse, allowMissing: true));

        $this->showPage(cookie: $cookie)
            ->assertDontSee('id="comble-guess-form"', false);
    }

    public function test_a_past_date_can_be_played_via_the_dated_route(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $this->showPage('2026-08-10')
            ->assertOk()
            ->assertSee('August 10, 2026')
            ->assertSee('5 guesses left');
    }

    public function test_playing_a_past_puzzle_does_not_affect_todays_progress(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $wrongGame = $this->makeGame(['name' => 'Wrong Game']);
        $wrongCharacter = $this->makeCharacter($wrongGame, 'Chun-Li');
        $wrongType = $this->makeType($wrongGame);

        $pastDate = '2026-08-10';

        $response = $this->submitGuess($this->guessPayload($wrongGame, $wrongCharacter, $wrongType), date: $pastDate);
        $response->assertRedirect(route('comble.show.date', ['date' => $pastDate]));

        $pastCookie = $this->cookieFromResponse($response);
        $this->assertSame('comble_'.$pastDate, $pastCookie['name']);

        $this->showPage($pastDate, cookie: $pastCookie)
            ->assertOk()
            ->assertSee('4 guesses left');

        $this->showPage(cookie: $pastCookie)
            ->assertOk()
            ->assertSee('5 guesses left');
    }

    public function test_future_dates_are_not_playable(): void
    {
        $tomorrow = Carbon::now()->addDay()->toDateString();

        $this->showPage($tomorrow)->assertNotFound();
    }

    public function test_an_invalid_calendar_date_404s(): void
    {
        $this->showPage('2026-02-30')->assertNotFound();
    }

    public function test_type_and_damage_hints_are_shown(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $correctType = $this->makeType($game, 'Combo');
        $wrongType = $this->makeType($game, 'Okizeme');
        $this->makeCombo($character, $correctType, ['damage' => 1000]);

        $response = $this->submitGuess($this->guessPayload($game, $character, $wrongType, 1000));

        $this->showPage(cookie: $this->cookieFromResponse($response))
            ->assertOk()
            ->assertSee('Okizeme')
            ->assertSee('Equal');
    }

    public function test_the_damage_guess_shows_a_higher_or_lower_hint(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type, ['damage' => 3000]);

        $response = $this->submitGuess($this->guessPayload($game, $character, $type, 1000));

        $this->showPage(cookie: $this->cookieFromResponse($response))
            ->assertOk()
            ->assertSee('1.000')
            ->assertSee('Higher');
    }

    public function test_the_reveal_grows_with_each_guess(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type, ['combo' => 'AAA BBB CCC DDD EEE']);

        $wrongGame = $this->makeGame(['name' => 'Wrong Game']);
        $wrongCharacter = $this->makeCharacter($wrongGame, 'Zangief');
        $wrongType = $this->makeType($wrongGame);

        $first = $this->submitGuess($this->guessPayload($wrongGame, $wrongCharacter, $wrongType));
        $cookie = $this->cookieFromResponse($first);

        $this->showPage(cookie: $cookie)
            ->assertSee('AAA')
            ->assertDontSee('BBB');

        $second = $this->submitGuess($this->guessPayload($wrongGame, $wrongCharacter, $wrongType), cookie: $cookie);
        $cookie = $this->cookieFromResponse($second);

        $this->showPage(cookie: $cookie)
            ->assertSee('AAA')
            ->assertSee('BBB')
            ->assertDontSee('CCC');
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

    private function makeType(Game $game, string $title = 'Combo'): GameEntry
    {
        return GameEntry::create(['title' => $title, 'gameid' => $game->idgame, 'order' => 0]);
    }

    private function makeCombo(Character $character, GameEntry $type, array $overrides = []): Combo
    {
        return Combo::create(array_merge([
            'combo' => 'AAA BBB CCC DDD EEE',
            'character_idcharacter' => $character->idcharacter,
            'submited' => now(),
            'type' => $type->entryid,
            'damage' => 3000,
        ], $overrides));
    }

    private function guessPayload(Game $game, Character $character, GameEntry $type, float $damage = 3000): array
    {
        return [
            'game_id' => $game->idgame,
            'character_id' => $character->idcharacter,
            'listing_type_id' => $type->entryid,
            'damage' => $damage,
        ];
    }

    /**
     * Submits a guess. Pass `date` for a past puzzle (routes to
     * comble.guess.date) or omit it for today's (comble.guess). Pass the
     * `cookie` returned by a prior cookieFromResponse() call to continue an
     * in-progress attempt.
     */
    private function submitGuess(array $payload, ?string $date = null, ?array $cookie = null): TestResponse
    {
        $route = $date ? route('comble.guess.date', ['date' => $date]) : route('comble.guess');
        $request = $cookie ? $this->withCookie($cookie['name'], $cookie['value']) : $this;

        return $request->post($route, $payload);
    }

    /**
     * Loads the Comble page. Pass `date` for a past puzzle (routes to
     * comble.show.date) or omit it for today's (comble.show).
     */
    private function showPage(?string $date = null, ?array $cookie = null): TestResponse
    {
        $route = $date ? route('comble.show.date', ['date' => $date]) : route('comble.show');
        $request = $cookie ? $this->withCookie($cookie['name'], $cookie['value']) : $this;

        return $request->get($route);
    }

    /**
     * Finds the per-day "comble_YYYY-MM-DD" cookie set on a response and
     * returns its decrypted name/value pair (withCookie() re-encrypts plain
     * values automatically for the next test request, so callers must pass
     * this back into submitGuess()/showPage(), not the raw already-encrypted
     * Set-Cookie value).
     */
    private function cookieFromResponse(TestResponse $response, bool $allowMissing = false): ?array
    {
        $raw = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'comble_'));

        if (! $raw) {
            if ($allowMissing) {
                return null;
            }

            $this->fail('Expected a "comble_*" cookie on the response.');
        }

        return ['name' => $raw->getName(), 'value' => $response->getCookie($raw->getName())->getValue()];
    }
}
