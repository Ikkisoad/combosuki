<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Http\Requests\StoreComboRequest;
use App\Http\Requests\UpdateComboRequest;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\ListModel;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ComboController extends Controller
{
    use FiltersCombos;

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
            ->with(['character', 'listingType', 'resources.resourceValue', 'user'])
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

    public function show(Combo $combo): View
    {
        $combo->load(['character.game', 'listingType', 'resources.resourceValue.gameResource', 'user']);

        $game = $combo->character->game;

        $primaryResources = $combo->resources
            ->filter(fn ($resource) => $resource->resourceValue?->gameResource?->primaryORsecundary === 1);

        $secondaryResources = $combo->resources
            ->filter(fn ($resource) => $resource->resourceValue?->gameResource?->primaryORsecundary === 0);

        $canEdit = Gate::allows('update', $combo);

        $characters = $listingTypes = $buttons = collect();

        if ($canEdit) {
            $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();
            $listingTypes = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();
            $buttons = $game->buttons()->orderBy('order')->get();
        }

        $userLists = collect();
        $comboListIds = [];

        if (auth()->check()) {
            $userLists = ListModel::where('game_idgame', $game->idgame)
                ->when(! auth()->user()->isTrusted(), fn (Builder $q) => $q->where('user_iduser', auth()->id()))
                ->orderBy('list_name')
                ->get();

            if ($userLists->isNotEmpty()) {
                $comboListIds = $combo->lists()->pluck('list.idlist')->all();
            }
        }

        return view('combos.show', [
            'combo' => $combo,
            'game' => $game,
            'primaryResources' => $primaryResources,
            'secondaryResources' => $secondaryResources,
            'canEdit' => $canEdit,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
            'buttons' => $buttons,
            'userLists' => $userLists,
            'comboListIds' => $comboListIds,
        ]);
    }

    public function create(Game $game): View
    {
        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        $listingTypes = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();

        $resources = GameResource::where('game_idgame', $game->idgame)
            ->whereIn('type', [1, 2])
            ->with('values')
            ->orderByDesc('primaryORsecundary')
            ->orderBy('text_name')
            ->get();

        return view('combos.create', [
            'game' => $game,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
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
            'type' => $validated['listingtype'],
            'patch' => $validated['patch'] ?? null,
            'user_iduser' => auth()->id(),
        ]);

        $this->syncResources($combo, $game, $validated['resources'] ?? []);

        return redirect()->route('combos.show', $combo)->with('status', 'Combo submitted.');
    }

    public function edit(Combo $combo): View
    {
        $this->authorize('update', $combo);

        $combo->load(['character.game', 'resources.resourceValue']);
        $game = $combo->character->game;

        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        $listingTypes = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();

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
            'listingTypes' => $listingTypes,
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
            'type' => $validated['listingtype'],
            'patch' => $validated['patch'] ?? null,
        ]);

        // The inline quick-edit form on the combo page doesn't send a `resources`
        // field at all (it only edits the simple/relational fields); only sync
        // resources when the submitted form actually included them, so a quick
        // edit doesn't wipe out the combo's existing resource values.
        if ($request->has('resources')) {
            $this->syncResources($combo, $game, $validated['resources'] ?? []);
        }

        return redirect()->route('combos.show', $combo)->with('status', 'Combo updated.');
    }

    public function destroy(Combo $combo): RedirectResponse
    {
        $this->authorize('delete', $combo);

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
