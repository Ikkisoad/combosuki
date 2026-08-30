<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Services\CombleDailyCombo;
use App\Services\CombleRevealer;
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

    /**
     * comble.show deliberately has no site chrome (no jumbotron/nav-bar —
     * see resources/views/comble/show.blade.php): Comble is opened as its
     * own browser tab from the main site's nav bar rather than navigated to
     * in place (see nav-bar.blade.php), so there's nothing on this page
     * whose job is linking back to the main site — except "View this
     * combo" once a puzzle is finished, which still points at combos.show
     * on the main site (App\Support\MainSiteUrl) regardless of which host
     * actually served this request. Requesting the route via an arbitrary
     * Host reproduces that without needing DISCORD_ACTIVITY_DOMAIN
     * configured at boot — comble.show has no domain constraint in this
     * (test) environment, so it matches regardless of Host, exactly like it
     * would if it genuinely lived on the main domain and a request came in
     * for some other host.
     */
    public function test_the_page_has_no_site_chrome_and_its_one_remaining_main_site_link_is_correct(): void
    {
        config(['app.url' => 'https://combosuki.com']);

        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $winResponse = $this->submitGuess($this->guessPayload($game, $character, $type));
        $cookie = $this->cookieFromResponse($winResponse);

        $html = $this->withCookie($cookie['name'], $cookie['value'])
            ->get('http://comble.example.test'.route('comble.show', absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('navbar', $html);
        $this->assertStringNotContainsString('jumbotron', $html);
        $this->assertStringContainsString('href="https://combosuki.com/combos/', $html);
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
        // A different title than the target's default 'Combo', so this
        // first guess is wrong on all three squares (type is now also
        // considered correct when its title matches the target's, even
        // across different games — see test_type_correctness_also_matches_by_title).
        $wrongType = $this->makeType($wrongGame, 'Okizeme');

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

    /**
     * The starter guess is optional, unlike game/character/type/damage — the
     * share text uses circles instead of squares for its result so that
     * stands out from the rest of the row.
     */
    public function test_the_share_texts_starter_result_uses_circles_not_squares(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type, ['combo' => 'AAA BBB CCC DDD EEE']);

        // Correct starter guess ('AAA BB' is the first 6 raw characters).
        $response = $this->submitGuess($this->guessPayload($game, $character, $type, 3000, 'AAA BB'));

        $shareText = $this->showPage(cookie: $this->cookieFromResponse($response))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('🟢', $shareText);
        $this->assertStringNotContainsString('🟩🟩🟩🟩', $shareText);
    }

    /**
     * Angle brackets are Discord's syntax for suppressing a link's embed —
     * without them, pasting the share text into Discord drops a big
     * "Comble" preview card under the message, drowning out the squares.
     */
    public function test_the_share_texts_link_is_wrapped_to_suppress_discord_embeds(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $response = $this->submitGuess($this->guessPayload($game, $character, $type));

        $this->showPage(cookie: $this->cookieFromResponse($response))
            ->assertOk()
            ->assertSee('<'.route('comble.show').'>');
    }

    /**
     * The page submits guesses via fetch (see resources/js/comble.js) so it
     * can update in place instead of reloading. An Accept: application/json
     * request must get the rendered fragment back directly, not a redirect.
     */
    public function test_a_guess_via_ajax_returns_updated_html_without_a_redirect(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $response = $this->postJson(route('comble.guess'), $this->guessPayload($game, $character, $type));

        $response->assertOk();
        $response->assertJsonStructure(['html']);
        $this->assertStringContainsString('You got it!', $response->json('html'));
        $this->assertNotNull($this->cookieFromResponse($response, allowMissing: true));
    }

    public function test_an_ajax_guess_on_a_finished_puzzle_returns_a_json_error(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $winResponse = $this->submitGuess($this->guessPayload($game, $character, $type));
        $cookie = $this->cookieFromResponse($winResponse);

        $response = $this->withCookie($cookie['name'], $cookie['value'])
            ->withCredentials()
            ->postJson(route('comble.guess'), $this->guessPayload($game, $character, $type));

        $response->assertStatus(409);
        $response->assertJson(['error' => 'That Comble puzzle is already finished.']);
    }

    public function test_an_ajax_guess_with_invalid_input_returns_json_validation_errors(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $response = $this->postJson(route('comble.guess'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['game_id', 'character_id', 'listing_type_id', 'damage']);
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

    /**
     * game_entry rows are per-game, so a "Combo" category in one game and a
     * "Combo" category in another are literally different entryids — but
     * they mean the same thing, so guessing a type titled the same as the
     * target's should count as correct even when it's a different game's
     * row entirely.
     */
    public function test_type_correctness_also_matches_by_title_across_different_games(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game, 'Combo');
        $this->makeCombo($character, $type);

        $otherGame = $this->makeGame(['name' => 'Other Game']);
        $otherCharacter = $this->makeCharacter($otherGame, 'Chun-Li');
        $otherType = $this->makeType($otherGame, 'Combo');

        $response = $this->submitGuess($this->guessPayload($otherGame, $otherCharacter, $otherType));

        $html = $this->showPage(cookie: $this->cookieFromResponse($response))->getContent();

        // Game/character are deliberately wrong here, so the only
        // "bg-success" cell in this row can be the type one.
        $this->assertStringContainsString('bg-success', $html);
    }

    /**
     * A correct type guess only matches by id within the game it was
     * guessed from (game_entry rows are per-game), so prefilling the next
     * guess's Type field by that id would silently fail to reselect
     * anything the moment the player switches to a different game. The
     * category name itself ("Combo") is what's actually correct, so the
     * sticky title must be rendered too — resources/js/comble.js falls back
     * to matching the new game's options by title when the id doesn't stick.
     */
    public function test_a_correct_type_guess_across_games_exposes_a_sticky_title_not_just_an_id(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game, 'Combo');
        $this->makeCombo($character, $type);

        $otherGame = $this->makeGame(['name' => 'Other Game']);
        $otherCharacter = $this->makeCharacter($otherGame, 'Chun-Li');
        $otherType = $this->makeType($otherGame, 'Combo');

        $response = $this->submitGuess($this->guessPayload($otherGame, $otherCharacter, $otherType));

        $html = $this->showPage(cookie: $this->cookieFromResponse($response))->getContent();

        $this->assertStringContainsString('data-sticky-title="Combo"', $html);
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

    public function test_a_correct_starter_guess_is_marked_correct(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type, ['combo' => 'AAA BBB CCC DDD EEE']);

        // First 6 raw characters of 'AAA BBB CCC DDD EEE', space included.
        $response = $this->submitGuess($this->guessPayload($game, $character, $type, 3000, 'AAA BB'));

        $this->showPage(cookie: $this->cookieFromResponse($response))
            ->assertOk()
            ->assertSee('AAA BB');
    }

    public function test_a_wrong_starter_guess_does_not_block_a_win(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type, ['combo' => 'AAA BBB CCC DDD EEE']);

        $response = $this->submitGuess($this->guessPayload($game, $character, $type, 3000, 'ZZZZZZ'));

        $this->showPage(cookie: $this->cookieFromResponse($response))
            ->assertOk()
            ->assertSee('You got it!')
            ->assertSee('ZZZZZZ');
    }

    public function test_a_partially_correct_starter_guess_is_shown_in_orange(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type, ['combo' => 'AAA BBB CCC DDD EEE']);

        // First 6 chars are 'AAA BB'; 'AAA XX' shares the first 4 positions
        // ('AAA ') but not the last 2 — some characters right, not all.
        $response = $this->submitGuess($this->guessPayload($game, $character, $type, 3000, 'AAA XX'));

        $this->showPage(cookie: $this->cookieFromResponse($response))
            ->assertOk()
            ->assertSee('AAA XX')
            ->assertSee('background-color: #fd7e14;', false);
    }

    /**
     * Laravel's global TrimStrings middleware silently strips leading/
     * trailing whitespace from request input by default — which would
     * always mark this guess wrong if 'starter' weren't specifically
     * excluded from it (see bootstrap/app.php), since a 5-character opening
     * move followed by a space is a completely ordinary combo shape.
     */
    public function test_a_starter_guess_with_a_trailing_space_is_compared_correctly(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type, ['combo' => '12345 BBB CCC DDD EEE']);

        $wrongGame = $this->makeGame(['name' => 'Wrong Game']);
        $wrongCharacter = $this->makeCharacter($wrongGame, 'Wrong Character');
        $wrongType = $this->makeType($wrongGame);

        // First 6 raw characters are '12345 ' — five digits plus the
        // trailing space before the next token.
        $response = $this->submitGuess($this->guessPayload($wrongGame, $wrongCharacter, $wrongType, 3000, '12345 '));

        $html = $this->showPage(cookie: $this->cookieFromResponse($response))->getContent();

        // Game/character/type are all deliberately wrong here, so a
        // "bg-success" cell in this row can only be the starter one.
        $this->assertStringContainsString('12345', $html);
        $this->assertStringContainsString('bg-success', $html);
        $this->assertStringNotContainsString('#fd7e14', $html);
    }

    public function test_the_starter_guess_is_optional(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $this->makeCombo($character, $type);

        $response = $this->submitGuess($this->guessPayload($game, $character, $type));
        $response->assertRedirect(route('comble.show'));

        $this->showPage(cookie: $this->cookieFromResponse($response))
            ->assertOk()
            ->assertSee('You got it!');
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

        $tokens = ['AAA', 'BBB', 'CCC', 'DDD', 'EEE'];

        $this->assertCount(0, $this->visibleTokens($this->showPage()->getContent(), $tokens));

        $first = $this->submitGuess($this->guessPayload($wrongGame, $wrongCharacter, $wrongType));
        $cookie = $this->cookieFromResponse($first);

        $visibleAfterOne = $this->visibleTokens($this->showPage(cookie: $cookie)->getContent(), $tokens);
        $this->assertCount(1, $visibleAfterOne);

        $second = $this->submitGuess($this->guessPayload($wrongGame, $wrongCharacter, $wrongType), cookie: $cookie);
        $cookie = $this->cookieFromResponse($second);

        $visibleAfterTwo = $this->visibleTokens($this->showPage(cookie: $cookie)->getContent(), $tokens);
        $this->assertCount(2, $visibleAfterTwo);
        $this->assertContains($visibleAfterOne[0], $visibleAfterTwo, 'a token revealed on an earlier guess must stay revealed');
    }

    /** Which of $tokens appear as literal, unredacted text in the rendered reveal. */
    private function visibleTokens(string $html, array $tokens): array
    {
        return array_values(array_filter($tokens, fn ($token) => str_contains($html, $token)));
    }

    /**
     * The reveal is meant to scatter revealed tokens across the whole combo,
     * not always uncover it left-to-right starting from the first token.
     * Across enough distinct puzzles, the first token should end up excluded
     * from the reveal at least once — under the old prefix-based reveal it
     * would be included every single time, so this reliably distinguishes
     * the two behaviors.
     */
    public function test_the_reveal_order_is_scattered_not_always_left_to_right(): void
    {
        $firstTokenAlwaysRevealed = true;

        foreach (range(1, 20) as $seed) {
            $game = $this->makeGame(['name' => 'Scatter Game '.$seed]);
            $character = $this->makeCharacter($game);
            $type = $this->makeType($game);
            $notation = implode(' ', array_map(fn ($i) => "T{$seed}x{$i}", range(0, 9)));
            $this->makeCombo($character, $type, ['combo' => $notation]);

            $html = app(CombleRevealer::class)->render($game, $notation, 1);

            if (! str_contains($html, "T{$seed}x0")) {
                $firstTokenAlwaysRevealed = false;

                break;
            }
        }

        $this->assertFalse($firstTokenAlwaysRevealed, 'expected at least one puzzle where the first token is not part of the reveal');
    }

    /**
     * The Starter field asks the player to guess the notation's first 6 raw
     * characters, so the progressive reveal must never expose all of them
     * itself — that would make the guess trivial. 'AAA BBB CCC DDD EEE FFF'
     * [0:6] is 'AAA BB', spanning the 'AAA' and 'BBB' tokens entirely and
     * partially respectively; both must never be revealed at the same time,
     * at any guess count the reveal is actually used for (1 through 4 — at
     * 5 the puzzle is finished and the real answer is shown in full anyway).
     * This specific 6-token notation is deliberately chosen: without the
     * starter-protecting reorder in CombleRevealer::revealOrder(), its
     * hash-based scatter order happens to reveal both 'AAA' and 'BBB'
     * together by guess 3, so this test would actually catch a regression
     * (unlike a naively-picked example that might pass by pure luck of the
     * hash ordering).
     */
    public function test_the_reveal_never_exposes_the_full_starter_answer_while_the_puzzle_is_in_progress(): void
    {
        $game = $this->makeGame();
        $character = $this->makeCharacter($game);
        $type = $this->makeType($game);
        $notation = 'AAA BBB CCC DDD EEE FFF';
        $this->makeCombo($character, $type, ['combo' => $notation]);

        foreach ([1, 2, 3, 4] as $guessesMade) {
            $html = app(CombleRevealer::class)->render($game, $notation, $guessesMade);

            $this->assertFalse(
                str_contains($html, 'AAA') && str_contains($html, 'BBB'),
                "at guessesMade={$guessesMade}, the reveal exposed the full 6-character starter answer"
            );
        }
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

    private function guessPayload(Game $game, Character $character, GameEntry $type, float $damage = 3000, ?string $starter = null): array
    {
        return array_filter([
            'game_id' => $game->idgame,
            'character_id' => $character->idcharacter,
            'listing_type_id' => $type->entryid,
            'damage' => $damage,
            'starter' => $starter,
        ], fn ($value) => $value !== null);
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
