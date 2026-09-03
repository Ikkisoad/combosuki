<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Button;
use App\Models\ButtonAlias;
use App\Models\Character;
use App\Models\CharacterButtonAlias;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait FiltersCombos
{
    /**
     * Ceiling on how many REPLACE() calls a combo search may nest around the
     * `combo` column while normalizing aliases and ignored buttons. Both are
     * admin-configured and unbounded, one nesting level each, and a deep
     * enough expression overflows SQLite's parser stack outright (31 levels
     * is already too many) — so past this point a search trades some recall
     * for still running. See applyFilters() for which levels get dropped.
     */
    private const MAX_NESTED_REPLACEMENTS = 15;

    /**
     * Run a combo search for $game scoped to $filters (a flat map of
     * FiltersCombos field names — see applyFilters()/applyOrdering() — to
     * values), returning up to $limit combos ordered the same way the
     * search page/Discord bot order results (damage desc by default).
     */
    private function searchCombos(Game $game, array $filters, int $limit): Collection
    {
        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->with('values')
            ->orderBy('text_name')
            ->get();

        $request = Request::create('/', 'GET', array_filter(
            $filters,
            fn ($value) => $value !== null && $value !== ''
        ));

        $query = Combo::query()
            ->with(['character', 'listingType', 'patch'])
            ->whereHas('character', fn (Builder $q) => $q->where('game_idgame', $game->idgame))
            ->visibleTo(auth()->user());

        $this->applyFilters($query, $request, $primaryResources, $game);
        $this->applyOrdering($query, $request);

        return $query->limit($limit)->get();
    }

    /**
     * Turn a $filters map (same shape searchCombos()/applyFilters() take)
     * into a list of plain-English criteria strings, e.g. "Starts with 2LK",
     * "Meter: 1 bar" — so callers can show searchers/challengers exactly
     * what a combo must satisfy to count, instead of relying on a free-text
     * label to convey it accurately. 'characterid' is intentionally not
     * described here since every caller already shows the character by name
     * alongside the query. When $character is given, resource value labels
     * use its per-character alias (e.g. "Support 3" becomes whatever that
     * character calls its 3rd resource value) the same way combo listings
     * already do via ResourceValue::aliasFor().
     */
    private function describeFilters(Game $game, array $filters, ?Character $character = null): array
    {
        $descriptions = [];

        if (($filters['combo'] ?? '') !== '') {
            $value = $filters['combo'];

            $descriptions[] = match ((int) ($filters['combolike'] ?? 0)) {
                1 => "Ends with \"{$value}\"",
                2 => "Contains \"{$value}\"",
                3 => "Does not contain \"{$value}\"",
                default => "Starts with \"{$value}\"",
            };
        }

        if (($filters['damage'] ?? '') !== '') {
            $descriptions[] = 'Damage ≤ '.$filters['damage'];
        }

        if (($filters['patch'] ?? '') !== '') {
            $descriptions[] = 'Patch: '.$filters['patch'];
        }

        foreach (array_filter(explode('#', (string) ($filters['comments'] ?? ''))) as $piece) {
            $descriptions[] = "Comments mention \"{$piece}\"";
        }

        foreach (array_filter(explode('#', (string) ($filters['notcomments'] ?? ''))) as $piece) {
            $descriptions[] = "Comments don't mention \"{$piece}\"";
        }

        if ($filters['novideo'] ?? false) {
            $descriptions[] = 'No video';
        } elseif (($filters['video'] ?? '') !== '') {
            $descriptions[] = "Video mentions \"{$filters['video']}\"";
        }

        if (($filters['listingtype'] ?? '-') !== '-' && ($filters['listingtype'] ?? '') !== '') {
            $title = GameEntry::where('gameid', $game->idgame)->where('entryid', $filters['listingtype'])->value('title');
            $descriptions[] = 'Type: '.($title ?? $filters['listingtype']);
        }

        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->with('values')
            ->get();

        foreach ($primaryResources as $resource) {
            $field = str_replace(' ', '_', $resource->text_name);
            $value = $filters[$field] ?? null;

            if ($resource->type === 1) {
                if ($value !== null && $value !== '' && $value !== '-') {
                    $resourceValue = $resource->values->firstWhere('idResources_values', (int) $value);
                    $label = $resourceValue?->aliasFor($character)?->alias ?? $resourceValue?->value ?? $value;
                    $descriptions[] = "{$resource->text_name}: {$label}";
                }
            } elseif ($resource->type === 2) {
                if ($value !== null && $value !== '' && $value !== '-') {
                    $operator = match ((int) ($filters[$field.'compare'] ?? 0)) {
                        2 => '=',
                        1 => '≥',
                        default => '≤',
                    };
                    $descriptions[] = "{$resource->text_name} {$operator} {$value}";
                }
            } elseif ($resource->type === 3) {
                $values = array_values(array_filter((array) $value, fn ($v) => $v !== null && $v !== '' && $v !== '-'));

                if ($values !== []) {
                    $labels = collect($values)->map(function ($v) use ($resource, $character) {
                        $resourceValue = $resource->values->firstWhere('idResources_values', (int) $v);

                        return $resourceValue?->aliasFor($character)?->alias ?? $resourceValue?->value ?? $v;
                    });
                    $descriptions[] = "{$resource->text_name}: ".$labels->implode(', ');
                }
            }
        }

        return $descriptions;
    }

    private function applyFilters(Builder $query, Request $request, $resources, Game $game): void
    {
        if ($request->filled('combo')) {
            $mode = $request->integer('combolike', 0);
            $value = $request->string('combo')->toString();

            $pattern = match ($mode) {
                1 => '%'.$value,
                2, 3 => '%'.$value.'%',
                default => $value.'%',
            };

            // A character-specific move alias is only mixed in when a
            // single character is selected (characterid filter set) — the
            // search pattern and stored notation are compared against one
            // shared SQL WHERE clause below, so there's no single alias set
            // to use once combos from multiple characters are in play.
            $characterAliases = collect();

            if ($request->filled('characterid') && $request->input('characterid') !== '-') {
                $character = Character::where('idcharacter', $request->integer('characterid'))
                    ->where('game_idgame', $game->idgame)
                    ->first();

                $characterAliases = $character
                    ? CharacterButtonAlias::where('character_idcharacter', $character->idcharacter)
                        ->with('button:idbutton,name')
                        ->get()
                    : collect();
            }

            // Longest alias first so a short alias that happens to be a
            // substring of a longer one can't clobber it mid-replacement.
            // Each alias expands to the name of an existing Button already
            // configured for this game, not arbitrary text, so it can't
            // point at notation the game doesn't actually use. Character
            // aliases are listed first so unique() (which keeps the first
            // occurrence) lets a character-specific alias override a
            // game-wide alias that happens to use the same word.
            $buttonAliases = $characterAliases
                ->concat(ButtonAlias::where('game_idgame', $game->idgame)->with('button:idbutton,name')->get())
                ->unique(fn ($alias) => mb_strtolower($alias->alias))
                ->sortByDesc(fn ($alias) => mb_strlen($alias->alias))
                ->values();

            // Case-insensitive: aliases are admin-defined words (e.g.
            // "Throw"), so a searcher typing "throw" should still match.
            foreach ($buttonAliases as $alias) {
                $pattern = str_ireplace($alias->alias, $alias->button->name, $pattern);
            }

            $ignoredTokens = [
                ' ',
                ...Button::where('game_idgame', $game->idgame)->where('ignored', true)->pluck('name'),
            ];

            $normalizedPattern = str_replace($ignoredTokens, '', $pattern);
            $operator = $mode === 3 ? 'NOT LIKE' : 'LIKE';

            // Aliases are expanded first (innermost) so stored notation that
            // literally uses an alias word normalizes the same way the
            // search pattern above just did, then ignored tokens are
            // stripped from the result exactly as before.
            //
            // Only the aliases that can change this search's outcome get
            // nested in, though. Each one wraps another REPLACE() around the
            // column, a game can configure arbitrarily many of them, and an
            // expression that deep is more than SQLite's parser will accept
            // (31 levels already fails with "parser stack overflow") besides
            // being run against every row. An alias rewrites stored notation
            // into its button's name, so it can only affect the comparison
            // when that name appears in the pattern the column is compared
            // against. An alias whose text contains a kept alias's text is
            // kept regardless of its own button name: dropping the longer
            // one while keeping the shorter one inside it would let the
            // short alias clobber it mid-replacement, which the
            // longest-first order above exists to prevent.
            $matchableAliases = $buttonAliases->filter(function ($alias) use ($ignoredTokens, $normalizedPattern) {
                $name = str_replace($ignoredTokens, '', $alias->button->name);

                return $name === '' || mb_stripos($normalizedPattern, $name) !== false;
            });

            $searchAliases = $buttonAliases
                ->filter(fn ($alias) => $matchableAliases->contains(
                    fn ($matchable) => mb_stripos($alias->alias, $matchable->alias) !== false
                ))
                // Still longest-first, so a game that configures more
                // matchable aliases than the ceiling allows loses its
                // shortest ones — the least specific, and the only ones that
                // could clobber a longer alias — rather than losing the
                // search itself to a database error.
                ->take(max(0, self::MAX_NESTED_REPLACEMENTS - count($ignoredTokens)))
                ->values();

            $strippedTokens = array_slice($ignoredTokens, 0, self::MAX_NESTED_REPLACEMENTS);

            $comboSql = 'combo';
            foreach ($searchAliases as $alias) {
                $comboSql = "REPLACE({$comboSql}, ?, ?)";
            }
            foreach ($strippedTokens as $token) {
                $comboSql = "REPLACE({$comboSql}, ?, '')";
            }

            $aliasBindings = [];
            foreach ($searchAliases as $alias) {
                $aliasBindings[] = $alias->alias;
                $aliasBindings[] = $alias->button->name;
            }

            $query->whereRaw("{$comboSql} {$operator} ?", [...$aliasBindings, ...$strippedTokens, $normalizedPattern]);
        }

        if ($request->filled('damage')) {
            $query->where('damage', '<=', $request->float('damage'));
        }

        if ($request->filled('patch')) {
            $pattern = $request->string('patch');
            $query->whereHas('patch', fn (Builder $q) => $q->where('label', 'like', $pattern));
        }

        foreach (array_filter(explode('#', (string) $request->input('comments'))) as $piece) {
            $query->where('comments', 'like', "%{$piece}%");
        }

        foreach (array_filter(explode('#', (string) $request->input('notcomments'))) as $piece) {
            $query->where('comments', 'not like', "%{$piece}%");
        }

        if ($request->boolean('novideo')) {
            $query->where(fn (Builder $q) => $q->whereNull('video')->orWhere('video', ''));
        } elseif ($request->filled('video')) {
            $query->where('video', 'like', '%'.$request->string('video').'%');
        }

        if ($request->filled('listingtype') && $request->input('listingtype') !== '-') {
            $query->where('type', $request->integer('listingtype'));
        }

        if ($request->filled('characterid') && $request->input('characterid') !== '-') {
            $query->where('character_idcharacter', $request->integer('characterid'));
        }

        foreach ($resources as $resource) {
            $field = str_replace(' ', '_', $resource->text_name);
            $value = $request->input($field);

            if ($resource->type === 1) {
                if ($value !== null && $value !== '-' && $value !== '') {
                    $query->whereHas('resources', fn (Builder $q) => $q->where('Resources_values_idResources_values', $value)
                    );
                }
            } elseif ($resource->type === 2) {
                if ($value !== null && $value !== '-' && $value !== '') {
                    $compareField = $field.'compare';
                    $operator = match ($request->integer($compareField, 0)) {
                        2 => '=',
                        1 => '>=',
                        default => '<=',
                    };

                    $query->whereHas('resources', function (Builder $q) use ($resource, $operator, $value) {
                        $q->where('number_value', $operator, $value)
                            ->whereHas('resourceValue', fn (Builder $q2) => $q2->where('game_resources_idgame_resources', $resource->idgame_resources)
                            );
                    });
                }
            } elseif ($resource->type === 3) {
                $this->applyDuplicatedResourceFilter($query, (array) $request->input($field, []));
            }
        }
    }

    private function applyDuplicatedResourceFilter(Builder $query, array $values): void
    {
        $values = array_values(array_filter($values, fn ($v) => $v !== null && $v !== '' && $v !== '-'));

        if (count($values) === 0) {
            return;
        }

        if (count($values) === 1) {
            $query->whereHas('resources', fn (Builder $q) => $q->where('Resources_values_idResources_values', $values[0]));

            return;
        }

        [$a, $b] = [(int) $values[0], (int) $values[1]];

        if ($a === $b) {
            $query->whereIn('idcombo', function ($sub) use ($a) {
                $sub->select('combo_idcombo')->from('resources')
                    ->where('Resources_values_idResources_values', $a)
                    ->groupBy('combo_idcombo')
                    ->havingRaw('COUNT(*) > 1');
            });

            return;
        }

        [$low, $high] = $a < $b ? [$a, $b] : [$b, $a];

        $query->whereIn('idcombo', function ($sub) use ($low, $high) {
            $sub->select('combo_idcombo')->from('resources')
                ->whereIn('Resources_values_idResources_values', [$low, $high])
                ->groupBy('combo_idcombo')
                ->havingRaw(
                    'GROUP_CONCAT(DISTINCT Resources_values_idResources_values ORDER BY Resources_values_idResources_values) = ?',
                    ["{$low},{$high}"]
                );
        });
    }

    private function applyOrdering(Builder $query, Request $request): void
    {
        $submitted = $request->input('Submitted');

        if ($submitted === '1') {
            $query->orderBy('submited')->orderByDesc('damage');
        } elseif ($submitted !== null && $submitted !== '-') {
            $query->orderByDesc('submited')->orderByDesc('damage');
        } else {
            $query->orderByDesc('damage');
        }

        $query->orderBy('idcombo');
    }
}
