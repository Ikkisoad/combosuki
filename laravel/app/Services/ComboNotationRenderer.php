<?php

namespace App\Services;

use App\Models\Button;
use App\Models\Character;
use App\Models\Game;
use Illuminate\Support\Collection;

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
     * Per-word tokenize() classification, namespaced by the $buttons
     * instance it was computed against (see tokenize()'s $cacheKey). Lives
     * for the lifetime of this renderer instance — safe under Laravel's
     * default per-request container resolution, which hands out a fresh
     * instance rather than a long-lived singleton.
     */
    private array $wordCache = [];

    /**
     * Split combo notation text into tokens, each either a literal string of
     * text or a recognized, genuinely color-coded button. Buttons are
     * matched in the game's configured order using each button's match_type
     * (exact/contains/starts_with/ends_with) against the word; among
     * matches, the first one with a non-default color wins, falling back to
     * the first match overall if every match is still at the default color
     * (in which case the token is treated as plain text). Returns
     * [['type' => 'text'|'colored', 'value' => $word, 'color' => ...]].
     *
     * $buttons lets a caller that already has $game's buttons loaded (e.g.
     * one tokenizing many notations in a loop, like ComboFlowChartBuilder)
     * skip the query this would otherwise run on every single call; omit it
     * to fetch fresh as before. Passing the *same* $buttons instance across
     * repeated calls also lets this reuse per-word classification work via
     * $wordCache (keyed by that instance's spl_object_id(), so a caller who
     * passes a different or omitted $buttons collection never risks a stale
     * hit from a previous, differently-scoped call) — the default,
     * fetch-fresh path (a new Collection every call, so never a cache hit)
     * is completely unaffected.
     */
    public function tokenize(Game $game, string $notation, ?Collection $buttons = null): array
    {
        $buttons ??= $game->buttons()->orderBy('order')->get(['name', 'color', 'match_type']);
        $cacheKey = spl_object_id($buttons);
        $this->wordCache[$cacheKey] ??= [];

        $tokens = [];
        $word = '';

        $flush = function (&$word) use (&$tokens, $buttons, $cacheKey) {
            if ($word === '') {
                return;
            }

            $lookup = mb_strtolower($word);

            $classification = $this->wordCache[$cacheKey][$lookup] ??= (function () use ($buttons, $word) {
                $matches = $buttons->filter(fn ($button) => $this->matches($button, $word));

                $button = $matches->first(fn ($button) => mb_strtolower($button->color) !== self::DEFAULT_COLOR)
                    ?? $matches->first();

                $isColored = $button && mb_strtolower($button->color) !== self::DEFAULT_COLOR;

                return $isColored ? ['colored' => true, 'color' => $button->color] : ['colored' => false];
            })();

            $tokens[] = $classification['colored']
                ? ['type' => 'colored', 'value' => $word, 'color' => $classification['color']]
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

    /**
     * Expand every button alias configured for $game (e.g. "Throw"), plus
     * any move alias specific to $character (e.g. "Tackle" for one
     * character only), found in $notation into the real button name it
     * stands for (e.g. "5LP"), so a combo written with aliases can be read
     * back in the game's actual button names. Longest alias first, same as
     * FiltersCombos::applyFilters(), so a short alias that's a substring of
     * a longer one can't clobber it mid-replacement. Case-insensitive since
     * aliases are admin-defined words a submitter may have typed in any
     * case.
     *
     * $aliases lets a caller that's already resolved $game/$character's
     * alias list (e.g. one resolving many notations for the same character
     * in a loop, like ComboFlowChartBuilder) skip the two queries
     * resolvedAliases() would otherwise run on every single call; pass the
     * result of a prior resolvedAliases() call, or omit it to resolve fresh
     * as before.
     */
    public function resolveAliases(Game $game, string $notation, ?Character $character = null, ?Collection $aliases = null): string
    {
        foreach ($aliases ?? $this->resolvedAliases($game, $character) as $alias) {
            $notation = str_ireplace($alias->alias, $alias->button->name, $notation);
        }

        return $notation;
    }

    /**
     * The alias list resolveAliases() replaces from, merging $character's
     * own move aliases with $game's button aliases. Character aliases are
     * listed first so unique() (which keeps the first occurrence) lets a
     * character-specific alias override a game-wide alias that happens to
     * use the same word, only for that character. Exposed (not just used
     * internally by resolveAliases()) so a caller resolving many notations
     * for the same $game/$character can compute this once and pass it back
     * into resolveAliases()'s $aliases parameter instead of paying for it
     * on every call.
     */
    public function resolvedAliases(Game $game, ?Character $character): Collection
    {
        $characterAliases = $character
            ? $character->buttonAliases()->with('button:idbutton,name')->get()
            : collect();

        $gameAliases = $game->buttonAliases()->with('button:idbutton,name')->get();

        return $characterAliases->concat($gameAliases)
            ->unique(fn ($alias) => mb_strtolower($alias->alias))
            ->sortByDesc(fn ($alias) => mb_strlen($alias->alias))
            ->values();
    }

    /**
     * Whether $word satisfies $button's match_type — exposed beyond the
     * renderer itself so callers that need the same button-matching (e.g.
     * ComboFlowChartBuilder, to find move boundaries) don't have to
     * reimplement it.
     */
    public function matches(Button $button, string $word): bool
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
