<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Combo;
use App\Models\Game;
use App\Models\GameResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait FiltersCombos
{
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
            ->with(['character', 'listingType'])
            ->whereHas('character', fn (Builder $q) => $q->where('game_idgame', $game->idgame));

        $this->applyFilters($query, $request, $primaryResources);
        $this->applyOrdering($query, $request);

        return $query->limit($limit)->get();
    }

    private function applyFilters(Builder $query, Request $request, $primaryResources): void
    {
        if ($request->filled('combo')) {
            $mode = $request->integer('combolike', 0);
            $value = $request->string('combo')->toString();

            $pattern = match ($mode) {
                1 => '%'.$value,
                2, 3 => '%'.$value.'%',
                default => $value.'%',
            };

            $normalizedPattern = str_replace([' ', '>'], '', $pattern);
            $operator = $mode === 3 ? 'NOT LIKE' : 'LIKE';

            $query->whereRaw("REPLACE(REPLACE(combo, ' ', ''), '>', '') {$operator} ?", [$normalizedPattern]);
        }

        if ($request->filled('damage')) {
            $query->where('damage', '<=', $request->float('damage'));
        }

        if ($request->filled('patch')) {
            $query->where('patch', 'like', $request->string('patch'));
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

        foreach ($primaryResources as $resource) {
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
