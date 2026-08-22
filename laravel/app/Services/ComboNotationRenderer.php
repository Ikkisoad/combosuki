<?php

namespace App\Services;

use App\Models\Button;
use App\Models\Game;

class ComboNotationRenderer
{
    /**
     * The color new buttons start with before an admin picks one. A button
     * still at this color is treated as "not really color-coded": it loses
     * matching priority to a button with a real color (see tokenize()), and
     * it doesn't force a space against a neighboring colored token (see
     * render()) — e.g. an uncolored "5" glues onto a colored "LK" as "5LK".
     */
    private const DEFAULT_COLOR = '#ffffff';

    /**
     * Split combo notation text into tokens, each either a literal string of
     * text or a recognized, genuinely color-coded button. Buttons are
     * matched in the game's configured order using each button's match_type
     * (exact/contains/starts_with/ends_with) against the word; among
     * matches, the first one with a non-default color wins, falling back to
     * the first match overall if every match is still at the default color
     * (in which case the token is treated as plain text). Returns
     * [['type' => 'text'|'colored', 'value' => $word, 'color' => ...]].
     */
    public function tokenize(Game $game, string $notation): array
    {
        $buttons = $game->buttons()->orderBy('order')->get(['name', 'color', 'match_type']);

        $tokens = [];
        $word = '';

        $flush = function (&$word) use (&$tokens, $buttons) {
            if ($word === '') {
                return;
            }

            $matches = $buttons->filter(fn ($button) => $this->matches($button, $word));

            $button = $matches->first(fn ($button) => mb_strtolower($button->color) !== self::DEFAULT_COLOR)
                ?? $matches->first();

            $isColored = $button && mb_strtolower($button->color) !== self::DEFAULT_COLOR;

            $tokens[] = $isColored
                ? ['type' => 'colored', 'value' => $word, 'color' => $button->color]
                : ['type' => 'text', 'value' => $word];

            $word = '';
        };

        foreach (mb_str_split($notation) as $char) {
            if ($char !== ' ') {
                $word .= $char;

                continue;
            }

            $flush($word);
        }

        $flush($word);

        return $tokens;
    }

    /**
     * Render tokenized notation as HTML, with colored tokens wrapped in a
     * styled span. The space between two tokens is dropped whenever exactly
     * one of them is color-coded, so an uncoded modifier reads as part of
     * the colored move next to it (e.g. "5 LK" renders as "5LK"); a space is
     * kept between two colored tokens (distinct moves) and between two
     * uncoded tokens. An uncoded token glued to a colored one this way
     * (e.g. the "5" in "5LK") is painted with that neighbor's color too, so
     * the whole glued unit reads as one move instead of part-white/part-
     * colored (see propagatedColor()).
     */
    public function render(Game $game, string $notation): string
    {
        $tokens = $this->tokenize($game, $notation);

        $html = '';
        $previousColored = null;

        foreach ($tokens as $index => $token) {
            $isColored = $token['type'] === 'colored';

            if ($previousColored !== null && $previousColored === $isColored) {
                $html .= ' ';
            }

            $word = e($token['value']);
            $color = $this->propagatedColor($tokens, $index);

            $html .= $color !== null
                ? '<span style="color: '.e($color).';">'.$word.'</span>'
                : $word;

            $previousColored = $isColored;
        }

        return $html;
    }

    /**
     * The color a token should render with, including propagation onto
     * uncoded tokens that are glued to a colored neighbor (no space between
     * them, per render()'s spacing rule). A colored token always uses its
     * own color. An uncoded token prefers the color of the colored token
     * that follows it — since a motion/modifier leads into the button after
     * it, e.g. "2" in "2 LK" belongs to "LK" — and falls back to the
     * colored token before it when there's no following one to glue to.
     */
    private function propagatedColor(array $tokens, int $index): ?string
    {
        $token = $tokens[$index];

        if ($token['type'] === 'colored') {
            return $token['color'];
        }

        $next = $tokens[$index + 1] ?? null;

        if ($next && $next['type'] === 'colored') {
            return $next['color'];
        }

        $previous = $tokens[$index - 1] ?? null;

        if ($previous && $previous['type'] === 'colored') {
            return $previous['color'];
        }

        return null;
    }

    private function matches(Button $button, string $word): bool
    {
        $needle = mb_strtolower($button->name);
        $haystack = mb_strtolower($word);

        return match ($button->match_type) {
            'starts_with' => str_starts_with($haystack, $needle),
            'ends_with' => str_ends_with($haystack, $needle),
            'contains' => str_contains($haystack, $needle),
            default => $haystack === $needle,
        };
    }
}
