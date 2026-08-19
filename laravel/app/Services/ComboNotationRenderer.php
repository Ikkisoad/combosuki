<?php

namespace App\Services;

use App\Models\Button;
use App\Models\Game;

class ComboNotationRenderer
{
    /**
     * Split combo notation text into tokens, each either a literal string of
     * text or a recognized button whose color should be applied. Buttons are
     * matched in the game's configured order, first match wins, using each
     * button's match_type (exact/contains/starts_with/ends_with) against the
     * word. Returns [['type' => 'text'|'colored', 'value' => ..., 'color' => ...]].
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

            $button = $buttons->first(fn ($button) => $this->matches($button, $word));

            $tokens[] = $button
                ? ['type' => 'colored', 'value' => ' '.$word.' ', 'color' => $button->color]
                : ['type' => 'text', 'value' => ' '.$word.' '];

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
