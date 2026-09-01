<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\Support\Collection;

class ComboFlowChartBuilder
{
    public function __construct(private ComboNotationRenderer $renderer) {}

    /**
     * The distinct moves that can legitimately follow $path among $combos
     * (already scoped by the caller — visibility, and whatever filters like
     * type or primary resources are active, via the same FiltersCombos::
     * applyFilters() every other combo listing uses): only combos whose
     * move sequence has $path as an exact prefix are considered, and the
     * result is whatever move each of those combos actually has next — not
     * "any move that has ever followed the current one in some combo",
     * which would let two unrelated combos get stitched into a path neither
     * of them actually contains. An empty $path matches every combo, so
     * this doubles as the character's list of combo starters.
     *
     * Returns [['key' => ..., 'label' => ..., 'color' => ..., 'count' => ...], ...],
     * most-observed first, ready for json_encode.
     */
    public function nextMoves(Character $character, Collection $combos, array $path): array
    {
        [$buttons, $ignoredButtons, $aliases] = $this->loadContext($character);
        $normalizedPath = array_map(fn ($value) => mb_strtolower((string) $value), array_filter($path, fn ($value) => $value !== null && $value !== ''));
        $depth = count($normalizedPath);

        $counts = [];

        foreach ($combos as $combo) {
            $moves = $this->moveTokens($character->game, (string) $combo->combo, $buttons, $ignoredButtons, $aliases);
            $keys = array_column($moves, 'key');

            if (array_slice($keys, 0, $depth) !== $normalizedPath) {
                continue;
            }

            $next = $moves[$depth] ?? null;

            if ($next === null) {
                continue;
            }

            // A move key that's purely numeric (e.g. a bare "9" jump input)
            // would otherwise be silently cast to a PHP int the moment it's
            // used as an array key here, and json_encode it as a JSON
            // number instead of a string — breaking the client's id
            // matching against it.
            $key = (string) $next['key'];

            $counts[$key] ??= ['key' => $key, 'label' => $next['label'], 'color' => $next['color'], 'count' => 0];
            $counts[$key]['count']++;
        }

        $moves = array_values($counts);
        usort($moves, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $moves;
    }

    /**
     * The combos among $combos (already scoped by the caller — same
     * visibility/filters as nextMoves()) whose move sequence *starts with*
     * $path (a list of move keys as produced by nextMoves(), e.g. from a
     * flow chart click-path). Returns an empty collection for an empty
     * $path, since "starts with nothing" would match every combo.
     */
    public function matchingCombos(Character $character, Collection $combos, array $path): Collection
    {
        $path = array_values(array_filter($path, fn ($value) => $value !== null && $value !== ''));

        if ($path === []) {
            return collect();
        }

        [$buttons, $ignoredButtons, $aliases] = $this->loadContext($character);
        $normalizedPath = array_map(fn ($value) => mb_strtolower((string) $value), $path);

        return $combos
            ->filter(function (Combo $combo) use ($character, $buttons, $ignoredButtons, $aliases, $normalizedPath) {
                $keys = array_column(
                    $this->moveTokens($character->game, (string) $combo->combo, $buttons, $ignoredButtons, $aliases),
                    'key'
                );

                return array_slice($keys, 0, count($normalizedPath)) === $normalizedPath;
            })
            ->values();
    }

    /**
     * Everything moveTokens() needs to process every one of $character's
     * combos without re-querying per combo: $character->game's buttons (for
     * tokenize()), the subset of those flagged ignored (e.g. the ">" chain
     * separator, for finding move boundaries), and $character's resolved
     * alias list (for resolveAliases()). nextMoves() and matchingCombos()
     * both loop over every combo for one character, so computing this once up
     * front instead of inside ComboNotationRenderer's per-call queries is
     * the difference between a handful of queries and thousands on a
     * character with hundreds of combos.
     */
    private function loadContext(Character $character): array
    {
        $game = $character->game;

        $buttons = $game->buttons()->orderBy('order')->get(['name', 'color', 'match_type', 'ignored']);
        $ignoredButtons = $buttons->filter(fn ($button) => (bool) $button->ignored);
        $aliases = $this->renderer->resolvedAliases($game, $character);

        return [$buttons, $ignoredButtons, $aliases];
    }

    /**
     * Split one combo's notation into its ordered list of moves. A move
     * boundary is either an ignored button (e.g. ">", dropped) or the start
     * of a second color-coded button within the same segment — so a game
     * whose admin color-codes buttons (e.g. "2A 2B" tokenizing as two
     * separately-colored words) still gets one node per button, while a
     * game that never got around to color-coding (every button still at
     * the renderer's default color, so tokenize() calls nothing "colored")
     * falls back to one move per ignored-separated segment instead of
     * producing no moves at all — color-coding is a rendering nicety, not
     * something a combo flow chart should depend on to find moves at all.
     * Whichever colored token starts a move lends it its color; an all-text
     * move (the fallback case) has none, and the client renders it in a
     * neutral gray.
     */
    private function moveTokens(Game $game, string $notation, Collection $buttons, Collection $ignoredButtons, Collection $aliases): array
    {
        $resolved = $this->renderer->resolveAliases($game, $notation, null, $aliases);
        $tokens = $this->renderer->tokenize($game, $resolved, $buttons);

        $moves = [];
        $words = [];
        $color = null;
        $sawColored = false;

        $flush = function () use (&$words, &$moves, &$color, &$sawColored) {
            if ($words === []) {
                return;
            }

            $label = implode(' ', $words);
            $moves[] = ['key' => mb_strtolower($label), 'label' => $label, 'color' => $color];

            $words = [];
            $color = null;
            $sawColored = false;
        };

        foreach ($tokens as $token) {
            $isIgnored = $ignoredButtons->contains(fn ($button) => $this->renderer->matches($button, $token['value']));

            if ($isIgnored) {
                $flush();

                continue;
            }

            $isColored = $token['type'] === 'colored';

            if ($isColored && $sawColored) {
                $flush();
            }

            $words[] = $token['value'];

            if ($isColored) {
                $sawColored = true;
                $color ??= $token['color'];
            }
        }

        $flush();

        return $moves;
    }
}
