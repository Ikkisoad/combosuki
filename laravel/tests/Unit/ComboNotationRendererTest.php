<?php

namespace Tests\Unit;

use App\Models\Button;
use App\Models\Game;
use App\Services\ComboNotationRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboNotationRendererTest extends TestCase
{
    use RefreshDatabase;

    private ComboNotationRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new ComboNotationRenderer();
    }

    private function makeGame(): Game
    {
        return Game::create(['name' => 'Test Game', 'complete' => 1, 'modPass' => 'secret']);
    }

    public function test_each_match_type_classifies_a_word_correctly(): void
    {
        $game = $this->makeGame();
        Button::create(['name' => 'LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => 'LP', 'color' => '#00ff00', 'match_type' => 'starts_with', 'game_idgame' => $game->idgame, 'order' => 2]);
        Button::create(['name' => 'HP', 'color' => '#0000ff', 'match_type' => 'ends_with', 'game_idgame' => $game->idgame, 'order' => 3]);
        Button::create(['name' => 'MK', 'color' => '#ffff00', 'match_type' => 'contains', 'game_idgame' => $game->idgame, 'order' => 4]);

        $tokens = $this->renderer->tokenize($game, 'LK LP2 2HP xMKx');

        $this->assertSame(['type' => 'colored', 'value' => 'LK', 'color' => '#ff0000'], $tokens[0]);
        $this->assertSame(['type' => 'colored', 'value' => 'LP2', 'color' => '#00ff00'], $tokens[1]);
        $this->assertSame(['type' => 'colored', 'value' => '2HP', 'color' => '#0000ff'], $tokens[2]);
        $this->assertSame(['type' => 'colored', 'value' => 'xMKx', 'color' => '#ffff00'], $tokens[3]);
    }

    public function test_button_still_at_default_color_yields_a_text_token_not_colored(): void
    {
        $game = $this->makeGame();
        Button::create(['name' => 'LK', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);

        $tokens = $this->renderer->tokenize($game, 'LK');

        $this->assertSame([['type' => 'text', 'value' => 'LK']], $tokens);
    }

    public function test_non_default_colored_match_wins_over_order(): void
    {
        $game = $this->makeGame();
        Button::create(['name' => 'LK', 'color' => '#ffffff', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => 'LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);

        $tokens = $this->renderer->tokenize($game, 'LK');

        $this->assertSame([['type' => 'colored', 'value' => 'LK', 'color' => '#ff0000']], $tokens);
    }

    public function test_unmatched_word_is_plain_text(): void
    {
        $game = $this->makeGame();
        Button::create(['name' => 'LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);

        $tokens = $this->renderer->tokenize($game, 'whiff');

        $this->assertSame([['type' => 'text', 'value' => 'whiff']], $tokens);
    }

    public function test_render_drops_the_space_between_exactly_one_colored_adjacent_token(): void
    {
        $game = $this->makeGame();
        Button::create(['name' => 'LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);

        $html = $this->renderer->render($game, '5 LK');

        $this->assertSame('<span style="color: #ff0000;">5</span><span style="color: #ff0000;">LK</span>', $html);
    }

    public function test_render_keeps_the_space_between_two_colored_tokens(): void
    {
        $game = $this->makeGame();
        Button::create(['name' => 'LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => 'HK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);

        $html = $this->renderer->render($game, 'LK HK');

        $this->assertSame(
            '<span style="color: #ff0000;">LK</span> <span style="color: #00ff00;">HK</span>',
            $html
        );
    }

    public function test_render_keeps_the_space_between_two_uncolored_tokens(): void
    {
        $game = $this->makeGame();

        $html = $this->renderer->render($game, 'whiff punish');

        $this->assertSame('whiff punish', $html);
    }

    public function test_uncolored_token_prefers_the_following_colored_neighbors_color_over_the_preceding_ones(): void
    {
        $game = $this->makeGame();
        Button::create(['name' => 'LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);
        Button::create(['name' => 'HK', 'color' => '#00ff00', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 2]);

        // "5" sits between a red token and a green one: it must pick up green (the following neighbor's color), not red.
        $html = $this->renderer->render($game, 'LK 5 HK');

        $this->assertSame(
            '<span style="color: #ff0000;">LK</span><span style="color: #00ff00;">5</span><span style="color: #00ff00;">HK</span>',
            $html
        );
    }

    public function test_uncolored_token_falls_back_to_preceding_colored_neighbor_when_no_following_one_exists(): void
    {
        $game = $this->makeGame();
        Button::create(['name' => 'LK', 'color' => '#ff0000', 'match_type' => 'exact', 'game_idgame' => $game->idgame, 'order' => 1]);

        $html = $this->renderer->render($game, 'LK 5');

        $this->assertSame(
            '<span style="color: #ff0000;">LK</span><span style="color: #ff0000;">5</span>',
            $html
        );
    }
}
