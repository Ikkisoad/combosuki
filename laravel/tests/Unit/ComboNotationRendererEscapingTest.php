<?php

namespace Tests\Unit;

use App\Models\Button;
use App\Models\Game;
use App\Services\CombleRevealer;
use App\Services\ComboNotationRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ComboNotationRenderer::render() and CombleRevealer::render() are the only
 * two places in the app that build HTML by string concatenation, and both are
 * rendered through {!! !!} (components/combo-notation.blade.php and
 * components/comble-reveal.blade.php). Everything they emit is attacker- or
 * admin-influenced: the notation is submitted by users, and the colour is
 * interpolated directly into a style="color: ..." attribute.
 *
 * The colour is worth stating plainly, because it looks harmless: Admin\
 * ButtonController validates it as ['string', 'max:7'] with no hex format
 * rule, so it is arbitrary seven-character text inside an HTML attribute. The
 * only thing preventing an attribute breakout on either path is the e() call
 * wrapping it. These tests exist so that e() cannot be quietly dropped.
 */
class ComboNotationRendererEscapingTest extends TestCase
{
    use RefreshDatabase;

    private ComboNotationRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new ComboNotationRenderer;
    }

    private function makeGame(): Game
    {
        return Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
    }

    private function makeButton(Game $game, string $name, string $color, string $matchType = 'exact'): Button
    {
        return Button::create([
            'name' => $name,
            'color' => $color,
            'match_type' => $matchType,
            'game_idgame' => $game->idgame,
            'order' => 1,
        ]);
    }

    public function test_render_escapes_a_script_tag_in_the_notation(): void
    {
        $html = $this->renderer->render($this->makeGame(), '<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_render_escapes_an_attribute_breakout_payload_in_the_notation(): void
    {
        $html = $this->renderer->render($this->makeGame(), '"><img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    /**
     * Six characters, comfortably inside ButtonController's max:7 — an admin
     * can set exactly this today. Without e() around the colour it would
     * close the style attribute and open an event handler.
     */
    public function test_render_escapes_a_quote_in_a_button_colour_so_it_cannot_break_out_of_the_style_attribute(): void
    {
        $game = $this->makeGame();
        $this->makeButton($game, 'LK', '"onerr');

        $html = $this->renderer->render($game, 'LK');

        $this->assertStringContainsString('style="color: &quot;onerr;"', $html);
        $this->assertStringNotContainsString('style="color: "onerr', $html);
    }

    public function test_render_escapes_an_angle_bracket_in_a_button_colour(): void
    {
        $game = $this->makeGame();
        $this->makeButton($game, 'LK', '<img');

        $html = $this->renderer->render($game, 'LK');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    /**
     * render() has two output branches — a bare escaped word, and a word
     * wrapped in a coloured <span>. A 'contains' button matching a hostile
     * word forces the second branch, so both are proven to escape.
     */
    public function test_render_escapes_a_notation_word_that_also_matches_a_coloured_button(): void
    {
        $game = $this->makeGame();
        $this->makeButton($game, 'script', '#ff0000', 'contains');

        $html = $this->renderer->render($game, '<script>alert(1)</script>');

        $this->assertStringContainsString('<span style="color: #ff0000;">', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_the_revealer_escapes_a_hostile_notation_and_colour_in_a_revealed_token(): void
    {
        $game = $this->makeGame();
        $this->makeButton($game, 'script', '"onerr', 'contains');

        // Every token revealed, so the hostile value takes the coloured
        // branch rather than being masked.
        $html = app(CombleRevealer::class)->render($game, '<script>alert(1)</script>', 5);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('style="color: "onerr', $html);
    }

    /**
     * The masked branch replaces the value with underscore blocks, so hostile
     * text can't leak through a token that hasn't been revealed yet either —
     * including its length being the only thing disclosed.
     */
    public function test_the_revealer_never_emits_an_unescaped_value_for_a_hidden_token(): void
    {
        $game = $this->makeGame();

        $html = app(CombleRevealer::class)->render($game, '<script>alert(1)</script>', 0);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringContainsString('▁', $html);
    }
}
