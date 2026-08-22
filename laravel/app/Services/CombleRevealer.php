<?php

namespace App\Services;

use App\Models\Game;

class CombleRevealer
{
    private const TOTAL_GUESSES = 5;

    /**
     * Renders combo notation with only a deterministically scattered subset
     * of its tokens revealed, using the same tokenization/coloring as
     * ComboNotationRenderer. Which tokens are revealed — not just how many —
     * is fixed per puzzle (derived from the notation itself, see
     * revealOrder()), so the reveal is spread across the whole combo instead
     * of always starting from the first token, while staying identical for
     * every player and every request for that puzzle. Unrevealed tokens
     * render as underscore blocks matching their original length. A space is
     * always kept next to a hidden token (so its length stays legible);
     * between two revealed tokens the usual glue rule applies (space dropped
     * when exactly one of the pair is color-coded).
     */
    public function render(Game $game, string $notation, int $guessesMade): string
    {
        $tokens = app(ComboNotationRenderer::class)->tokenize($game, $notation);
        $revealed = $this->revealedIndices($tokens, $notation, $guessesMade);

        $html = '';
        $previous = null;

        foreach ($tokens as $index => $token) {
            $isRevealed = isset($revealed[$index]);
            $isColored = $token['type'] === 'colored';

            if ($previous !== null) {
                $needsSpace = ! $isRevealed || ! $previous['revealed']
                    ? true
                    : $previous['colored'] === $isColored;

                if ($needsSpace) {
                    $html .= ' ';
                }
            }

            $html .= $isRevealed
                ? ($isColored
                    ? '<span style="color: '.e($token['color']).';">'.e($token['value']).'</span>'
                    : e($token['value']))
                : '<span class="comble-hidden-token">'.str_repeat('▁', mb_strlen($token['value'])).'</span>';

            $previous = ['revealed' => $isRevealed, 'colored' => $isColored];
        }

        return $html;
    }

    /**
     * Same reveal as render(), as plain space-joined text with no HTML/color
     * — for surfaces that can't render inline color spans, e.g. a Discord
     * embed description.
     */
    public function renderPlain(Game $game, string $notation, int $guessesMade): string
    {
        $tokens = app(ComboNotationRenderer::class)->tokenize($game, $notation);
        $revealed = $this->revealedIndices($tokens, $notation, $guessesMade);

        return collect($tokens)
            ->map(fn (array $token, int $index) => isset($revealed[$index])
                ? $token['value']
                : str_repeat('▁', mb_strlen($token['value'])))
            ->implode(' ');
    }

    /**
     * The set of revealed token indices, keyed for O(1) lookup. How many are
     * revealed grows with guessesMade; which ones never changes once
     * revealed, since each guess only extends the same fixed order.
     */
    private function revealedIndices(array $tokens, string $notation, int $guessesMade): array
    {
        $tokenCount = count($tokens);

        $count = $guessesMade <= 0
            ? 0
            : (int) min($tokenCount, ceil($guessesMade / self::TOTAL_GUESSES * $tokenCount));

        return array_flip(array_slice($this->revealOrder($tokenCount, $notation), 0, $count));
    }

    /**
     * A deterministic permutation of token indices [0..tokenCount-1], fixed
     * per notation string: sorting indices by a hash of "notation|index"
     * scatters the order across the whole combo without needing to store
     * anything, and without touching PHP's global RNG state (mt_srand()
     * would work but leaks into any other randomness generated later in the
     * same request).
     */
    private function revealOrder(int $tokenCount, string $notation): array
    {
        if ($tokenCount === 0) {
            return [];
        }

        $indices = range(0, $tokenCount - 1);

        usort($indices, fn (int $a, int $b) => hash('sha256', $notation.'|'.$a) <=> hash('sha256', $notation.'|'.$b));

        return $indices;
    }
}
