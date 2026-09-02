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

            $color = $isRevealed ? $this->displayColor($tokens, $revealed, $index) : null;

            $html .= $isRevealed
                ? ($color !== null
                    ? '<span style="color: '.e($color).';">'.e($token['value']).'</span>'
                    : e($token['value']))
                : '<span class="comble-hidden-token">'.str_repeat('▁', mb_strlen($token['value'])).'</span>';

            $previous = ['revealed' => $isRevealed, 'colored' => $isColored];
        }

        return $html;
    }

    /**
     * Same color-propagation rule as ComboNotationRenderer::propagatedColor()
     * — an uncoded token glued to a colored neighbor takes that neighbor's
     * color — but only from a neighbor that is itself revealed, so an
     * uncoded token never leaks the color (and thus the identity) of a
     * still-hidden button next to it.
     */
    private function displayColor(array $tokens, array $revealed, int $index): ?string
    {
        $token = $tokens[$index];

        if ($token['type'] === 'colored') {
            return $token['color'];
        }

        $next = $tokens[$index + 1] ?? null;

        if ($next && $next['type'] === 'colored' && isset($revealed[$index + 1])) {
            return $next['color'];
        }

        $previous = $tokens[$index - 1] ?? null;

        if ($previous && $previous['type'] === 'colored' && isset($revealed[$index - 1])) {
            return $previous['color'];
        }

        return null;
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
     *
     * Any token overlapping the notation's first 6 characters with spaces
     * stripped out — the "Starter" guess field's answer — sorts after every
     * other token, no
     * matter what the hash comparison says. Since this method is only ever
     * consulted while the puzzle isn't finished, guessesMade is at most 4,
     * and CombleDailyCombo guarantees at least 5 tokens, the reveal count
     * (ceil(guessesMade/5 * tokenCount)) never reaches tokenCount itself —
     * so whichever of these deprioritized tokens lands last is guaranteed to
     * stay hidden for the whole game, and the Starter answer is never fully
     * given away by the reveal alone.
     */
    private function revealOrder(int $tokenCount, string $notation): array
    {
        if ($tokenCount === 0) {
            return [];
        }

        $protected = $this->starterOverlappingTokenIndices($notation);

        $indices = range(0, $tokenCount - 1);

        usort($indices, function (int $a, int $b) use ($protected, $notation) {
            $aProtected = isset($protected[$a]);
            $bProtected = isset($protected[$b]);

            if ($aProtected !== $bProtected) {
                return $aProtected ? 1 : -1;
            }

            return hash('sha256', $notation.'|'.$a) <=> hash('sha256', $notation.'|'.$b);
        });

        return $indices;
    }

    /**
     * Token indices whose span overlaps the notation's first 6 characters
     * with spaces stripped out (positions 0-5 of the non-space text) —
     * mirrors ComboNotationRenderer::tokenize()'s own word-splitting
     * exactly, so the indices line up with $tokens, but tracks each word's
     * starting character offset (counted over non-space characters only)
     * instead of just its text. Spaces are excluded from that offset so
     * this stays aligned with CombleGuessEvaluator::starterResult(), which
     * compares the guess against the same space-stripped first 6
     * characters — a space in the raw notation must never shift the
     * boundary and leave part of the real answer unprotected.
     */
    private function starterOverlappingTokenIndices(string $notation): array
    {
        $protected = [];
        $tokenIndex = 0;
        $word = '';
        $wordStart = null;
        $charIndex = 0;

        $flush = function () use (&$word, &$wordStart, &$tokenIndex, &$protected) {
            if ($word === '') {
                return;
            }

            if ($wordStart < 6) {
                $protected[$tokenIndex] = true;
            }

            $tokenIndex++;
            $word = '';
        };

        foreach (mb_str_split($notation) as $char) {
            if ($char !== ' ') {
                if ($word === '') {
                    $wordStart = $charIndex;
                }

                $word .= $char;
                $charIndex++;

                continue;
            }

            $flush();
        }

        $flush();

        return $protected;
    }
}
