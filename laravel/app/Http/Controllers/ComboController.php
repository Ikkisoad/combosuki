<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreComboRequest;
use App\Http\Requests\UpdateComboRequest;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComboController extends Controller
{
    /**
     * Browse/search combos for a game. Mirrors legacy's forms.php (search
     * mode) + submit.php (result query) combined into one page: the filter
     * form and its results live together instead of being two separate
     * pages joined by a GET redirect.
     */
    public function index(Game $game, Request $request): View
    {
        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        $listingTypes = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();

        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->with('values')
            ->orderBy('text_name')
            ->get();

        $query = Combo::query()
            ->with(['character', 'listingType', 'resources.resourceValue'])
            ->whereHas('character', fn (Builder $q) => $q->where('game_idgame', $game->idgame));

        $this->applyFilters($query, $request, $primaryResources);

        $this->applyOrdering($query, $request);

        $combos = $query->paginate(20)->withQueryString();

        return view('combos.index', [
            'game' => $game,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
            'primaryResources' => $primaryResources,
            'combos' => $combos,
        ]);
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

        if ($request->filled('video')) {
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

    public function show(Combo $combo): View
    {
        $combo->load(['character.game', 'listingType', 'resources.resourceValue.gameResource']);

        $primaryResources = $combo->resources
            ->filter(fn ($resource) => $resource->resourceValue?->gameResource?->primaryORsecundary === 1);

        $secondaryResources = $combo->resources
            ->filter(fn ($resource) => $resource->resourceValue?->gameResource?->primaryORsecundary === 0);

        return view('combos.show', [
            'combo' => $combo,
            'game' => $combo->character->game,
            'primaryResources' => $primaryResources,
            'secondaryResources' => $secondaryResources,
        ]);
    }

    public function create(Game $game): View
    {
        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        $resources = GameResource::where('game_idgame', $game->idgame)
            ->whereIn('type', [1, 2])
            ->with('values')
            ->orderByDesc('primaryORsecundary')
            ->orderBy('text_name')
            ->get();

        return view('combos.create', [
            'game' => $game,
            'characters' => $characters,
            'resources' => $resources,
        ]);
    }

    public function store(StoreComboRequest $request, Game $game): RedirectResponse
    {
        $validated = $request->validated();

        $combo = Combo::create([
            'combo' => $validated['combo'],
            'comments' => $validated['comments'] ?? null,
            'video' => $validated['video'] ?? null,
            'character_idcharacter' => $validated['character_idcharacter'],
            'submited' => now(),
            'damage' => $validated['damage'] ?? null,
            'type' => $request->integer('listingtype') ?: 0,
            'patch' => $validated['patch'] ?? null,
            'user_iduser' => auth()->id(),
        ]);

        $this->syncResources($combo, $game, $validated['resources'] ?? []);

        return redirect()->route('combos.show', $combo)->with('status', 'Combo submitted.');
    }

    public function edit(Combo $combo): View
    {
        $combo->load(['character.game', 'resources.resourceValue']);
        $game = $combo->character->game;

        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        $resources = GameResource::where('game_idgame', $game->idgame)
            ->whereIn('type', [1, 2])
            ->with('values')
            ->orderByDesc('primaryORsecundary')
            ->orderBy('text_name')
            ->get();

        $selectedResources = [];

        foreach ($combo->resources as $resource) {
            $gameResourceId = $resource->resourceValue?->game_resources_idgame_resources;

            if ($gameResourceId === null) {
                continue;
            }

            $selectedResources[$gameResourceId] = [
                'value_id' => $resource->Resources_values_idResources_values,
                'number_value' => $resource->number_value,
            ];
        }

        return view('combos.edit', [
            'game' => $game,
            'combo' => $combo,
            'characters' => $characters,
            'resources' => $resources,
            'selectedResources' => $selectedResources,
        ]);
    }

    public function update(UpdateComboRequest $request, Combo $combo): RedirectResponse
    {
        $validated = $request->validated();
        $game = $combo->character->game;

        // TODO: record which user made this edit once an audit/edit-log exists
        $combo->update([
            'combo' => $validated['combo'],
            'comments' => $validated['comments'] ?? null,
            'video' => $validated['video'] ?? null,
            'character_idcharacter' => $validated['character_idcharacter'],
            'damage' => $validated['damage'] ?? null,
            'patch' => $validated['patch'] ?? null,
        ]);

        $this->syncResources($combo, $game, $validated['resources'] ?? []);

        return redirect()->route('combos.show', $combo)->with('status', 'Combo updated.');
    }

    public function destroy(Combo $combo): RedirectResponse
    {
        $game = $combo->character->game;

        // TODO: record which user made this edit once an audit/edit-log exists
        $combo->delete();

        return redirect()->route('games.combos.index', $game)->with('status', 'Combo deleted.');
    }

    private function syncResources(Combo $combo, Game $game, array $resources): void
    {
        $combo->resources()->delete();

        foreach ($resources as $idGameResources => $value) {
            if ($value === null || $value === '' || $value === '-') {
                continue;
            }

            $gameResource = GameResource::find($idGameResources);

            if (! $gameResource || $gameResource->game_idgame !== $game->idgame) {
                continue;
            }

            if ($gameResource->type === 1) {
                Resource::create([
                    'combo_idcombo' => $combo->idcombo,
                    'Resources_values_idResources_values' => (int) $value,
                    'number_value' => null,
                ]);
            } elseif ($gameResource->type === 2) {
                foreach ($gameResource->values as $resourceValue) {
                    Resource::create([
                        'combo_idcombo' => $combo->idcombo,
                        'Resources_values_idResources_values' => $resourceValue->idResources_values,
                        'number_value' => (float) $value,
                    ]);
                }
            }
        }
    }
}
