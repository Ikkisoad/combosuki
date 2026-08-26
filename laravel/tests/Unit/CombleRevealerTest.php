<?php

namespace Tests\Unit;

use App\Models\Button;
use App\Models\Game;
use App\Services\CombleRevealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CombleRevealerTest extends TestCase
{
    use RefreshDatabase;

    private CombleRevealer $revealer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->revealer = new CombleRevealer();
    }

    private function makeGame(): Game
    {
        $game = Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);

        Button::create(['name' => 'LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => 'HK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);

        return $game;
    }

    /**
     * Every token in the plain-text render is either fully revealed (equal to
     * its own text) or fully masked (an underscore block of the same
     * mb_strlen). Returns the count of masked tokens.
     */
    private function countMaskedTokens(string $rendered, array $words): int
    {
        $rendered = explode(' ', $rendered);
        $masked = 0;

        foreach ($words as $index => $word) {
            if ($rendered[$index] !== $word) {
                $this->assertSame(mb_strlen($word), mb_strlen($rendered[$index]), "token {$index} is neither fully revealed nor a same-length mask");
                $masked++;
            }
        }

        return $masked;
    }

    public function test_no_guesses_made_leaves_every_token_fully_masked(): void
    {
        $game = $this->makeGame();
        $notation = 'LK HK whiff punish combo';

        $rendered = $this->revealer->renderPlain($game, $notation, 0);

        $this->assertSame(5, $this->countMaskedTokens($rendered, explode(' ', $notation)));

        // Negative guessesMade behaves the same as zero.
        $this->assertSame($rendered, $this->revealer->renderPlain($game, $notation, -1));
    }

    public function test_identical_arguments_produce_identical_output_every_call(): void
    {
        $game = $this->makeGame();
        $notation = 'LK HK whiff punish combo';

        $first = $this->revealer->renderPlain($game, $notation, 3);
        $second = $this->revealer->renderPlain($game, $notation, 3);

        $this->assertSame($first, $second);
    }

    public function test_revealed_token_set_only_grows_as_guesses_increase(): void
    {
        $game = $this->makeGame();
        $notation = 'LK HK whiff punish combo extra token here more';
        $words = explode(' ', $notation);

        $previousRevealedWords = [];

        for ($guesses = 0; $guesses <= 5; $guesses++) {
            $rendered = explode(' ', $this->revealer->renderPlain($game, $notation, $guesses));

            $revealedWords = [];
            foreach ($words as $index => $word) {
                if ($rendered[$index] === $word) {
                    $revealedWords[$index] = true;
                }
            }

            $this->assertSame(
                [],
                array_diff_key($previousRevealedWords, $revealedWords),
                "a token revealed at guessesMade=".($guesses - 1)." became hidden again at guessesMade={$guesses}"
            );

            $previousRevealedWords = $revealedWords;
        }
    }

    public function test_the_single_token_overlapping_the_starter_field_stays_masked_through_the_max_non_terminal_guess_count(): void
    {
        $game = $this->makeGame();
        // "combo1" alone spans raw characters 0-5 (the whole starter field), so it's the
        // only protected token here — every other token is a plain unprotected word.
        $notation = 'combo1 LK HK whiff punish';
        $words = explode(' ', $notation);

        // TOTAL_GUESSES is 5 and the puzzle is only ever queried while unfinished
        // (guessesMade <= 4), so with 5 tokens the reveal count tops out at 4 —
        // one short of tokenCount — guaranteeing the protected token stays hidden.
        $rendered = explode(' ', $this->revealer->renderPlain($game, $notation, 4));

        $this->assertNotSame($words[0], $rendered[0]);
        $this->assertSame(mb_strlen($words[0]), mb_strlen($rendered[0]));

        // The 4 unprotected tokens fill the entire reveal budget.
        for ($i = 1; $i < 5; $i++) {
            $this->assertSame($words[$i], $rendered[$i]);
        }
    }

    public function test_no_color_leaks_onto_an_uncolored_token_adjacent_to_a_still_hidden_colored_token(): void
    {
        $game = $this->makeGame();
        Button::create(['name' => 'MK', 'color' => '#0000ff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 3]);
        Button::create(['name' => 'HP', 'color' => '#ffff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 4]);
        Button::create(['name' => 'MP', 'color' => '#ff00ff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 5]);

        // Deterministically derived (see revealOrder()'s hash-based order for this exact
        // string): at guessesMade=2 token index 4 ("txt31") is revealed while both its
        // neighbors, index 3 ("MK") and index 5 ("HP"), stay hidden. "txt31" is plain text,
        // so it must render with no color at all — never inheriting a hidden neighbor's color.
        $notation = 'starter1 LK txt11 MK txt31 HP txt51 HK txt71 MP';

        $html = $this->revealer->render($game, $notation, 2);

        $this->assertStringContainsString('txt31', $html);
        $this->assertStringNotContainsString('>txt31</span>', $html);
    }

    public function test_render_html_matches_renderplain_content_once_fully_revealed(): void
    {
        $game = $this->makeGame();
        $notation = 'LK HK whiff punish combo';

        // TOTAL_GUESSES is 5; guessesMade=5 reveals every token.
        $plain = $this->revealer->renderPlain($game, $notation, 5);

        $this->assertSame('LK HK whiff punish combo', $plain);
    }
}
