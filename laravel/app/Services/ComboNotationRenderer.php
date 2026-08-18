<?php

namespace App\Services;

use App\Models\Game;

class ComboNotationRenderer
{
    /**
     * Split combo notation text into tokens, each either a literal string of
     * text or a recognized button whose PNG filename should be rendered.
     * Returns [['type' => 'text'|'button', 'value' => ...], ...] so the Blade
     * component can escape text and safely build image paths for buttons.
     */
    public function tokenize(Game $game, string $notation): array
    {
        $buttons = $game->buttons()->get(['name', 'png'])
            ->keyBy(fn ($button) => mb_strtolower($button->name));

        $tokens = [];
        $word = '';

        $flush = function (&$word) use (&$tokens, $buttons) {
            if ($word === '') {
                return;
            }

            $button = $buttons->get(mb_strtolower($word));

            $tokens[] = $button
                ? ['type' => 'button', 'value' => $button->png]
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
}
