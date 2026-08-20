<?php

namespace App\Services;

use App\Models\Game;

class CombleRevealer
{
    private const TOTAL_GUESSES = 5;

    /**
     * Renders combo notation with only a growing prefix of its tokens
     * revealed, using the same tokenization/coloring as ComboNotationRenderer.
     * Unrevealed tokens render as underscore blocks matching their original
     * length. A space is always kept next to a hidden token (so its length
     * stays legible); between two revealed tokens the usual glue rule
     * applies (space dropped when exactly one of the pair is color-coded).
     */
    public function render(Game $game, string $notation, int $guessesMade): string
    {
        $tokens = app(ComboNotationRenderer::class)->tokenize($game, $notation);
        $revealedCount = $this->revealedCount($tokens, $guessesMade);

        $html = '';
        $previous = null;

        foreach ($tokens as $index => $token) {
            $revealed = $index < $revealedCount;
            $isColored = $token['type'] === 'colored';

            if ($previous !== null) {
                $needsSpace = ! $revealed || ! $previous['revealed']
                    ? true
                    : $previous['colored'] === $isColored;

                if ($needsSpace) {
                    $html .= ' ';
                }
            }

            $html .= $revealed
                ? ($isColored
                    ? '<span style="color: '.e($token['color']).';">'.e($token['value']).'</span>'
                    : e($token['value']))
                : '<span class="comble-hidden-token">'.str_repeat('▁', mb_strlen($token['value'])).'</span>';

            $previous = ['revealed' => $revealed, 'colored' => $isColored];
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
        $revealedCount = $this->revealedCount($tokens, $guessesMade);

        return collect($tokens)
            ->map(fn (array $token, int $index) => $index < $revealedCount
                ? $token['value']
                : str_repeat('▁', mb_strlen($token['value'])))
            ->implode(' ');
    }

    private function revealedCount(array $tokens, int $guessesMade): int
    {
        $tokenCount = count($tokens);

        return $guessesMade <= 0
            ? 0
            : (int) min($tokenCount, ceil($guessesMade / self::TOTAL_GUESSES * $tokenCount));
    }
}
