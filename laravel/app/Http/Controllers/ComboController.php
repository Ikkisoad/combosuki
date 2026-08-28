<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Http\Requests\StoreComboRequest;
use App\Http\Requests\UpdateComboRequest;
use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\ListModel;
use App\Services\ComboSubmissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ComboController extends Controller
{
    use FiltersCombos;

    public function __construct(private ComboSubmissionService $comboSubmission) {}

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
            ->whereHas('character', fn (Builder $q) => $q->where('game_idgame', $game->idgame))
            ->visibleTo(auth()->user());

        $this->applyFilters($query, $request, $primaryResources, $game);

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
        $combo->load(['character.game', 'listingType', 'resources.resourceValue.gameResource', 'user', 'verifier']);
        $combo->increment('views');

        $game = $combo->character->game;

        $primaryResources = $combo->resources
            ->filter(fn ($resource) => $resource->resourceValue?->gameResource?->primaryORsecundary === 1);

        $secondaryResources = $combo->resources
            ->filter(fn ($resource) => $resource->resourceValue?->gameResource?->primaryORsecundary === 0);

        $similarCombos = $this->similarCombos($combo);

        $canEdit = Gate::allows('update', $combo);
        $canVerify = Gate::allows('verify', $combo);

        $characters = $listingTypes = $buttons = collect();

        if ($canEdit) {
            $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();
            $listingTypes = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();
            $buttons = $game->buttons()->orderBy('order')->get();
        }

        $userLists = collect();
        $comboListIds = [];
        $isFavorited = false;

        if (auth()->check()) {
            $userLists = ListModel::where('game_idgame', $game->idgame)
                ->when(! auth()->user()->isTrusted(), fn (Builder $q) => $q->where('user_iduser', auth()->id()))
                ->orderBy('list_name')
                ->get();

            if ($userLists->isNotEmpty()) {
                $comboListIds = $combo->lists()->pluck('list.idlist')->all();
            }

            $isFavorited = (bool) auth()->user()->favoriteGuide?->combos()
                ->where('combo_listing.idcombo', $combo->idcombo)
                ->exists();
        }

        return view('combos.show', [
            'combo' => $combo,
            'game' => $game,
            'primaryResources' => $primaryResources,
            'secondaryResources' => $secondaryResources,
            'canEdit' => $canEdit,
            'canVerify' => $canVerify,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
            'buttons' => $buttons,
            'userLists' => $userLists,
            'comboListIds' => $comboListIds,
            'isFavorited' => $isFavorited,
            'similarCombos' => $similarCombos,
        ]);
    }

    /**
     * Other combos for the same character and listing type with the exact
     * same set of resource values configured (e.g. same position, same
     * starter, same assists) as $combo.
     */
    private function similarCombos(Combo $combo): Collection
    {
        $resourceValueIds = $combo->resources
            ->pluck('Resources_values_idResources_values')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($resourceValueIds->isEmpty()) {
            return new Collection;
        }

        $resourceValueIdList = $resourceValueIds->implode(',');

        return Combo::query()
            ->where('character_idcharacter', $combo->character_idcharacter)
            ->where('type', $combo->type)
            ->where('idcombo', '!=', $combo->idcombo)
            ->whereIn('idcombo', function ($query) use ($resourceValueIdList) {
                $query->select('combo_idcombo')
                    ->from('resources')
                    ->groupBy('combo_idcombo')
                    ->havingRaw(
                        'GROUP_CONCAT(DISTINCT Resources_values_idResources_values ORDER BY Resources_values_idResources_values) = ?',
                        [$resourceValueIdList]
                    );
            })
            ->orderByDesc('damage')
            ->limit(8)
            ->get();
    }

    public function create(Game $game, Request $request): View
    {
        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        $listingTypes = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();

        $resources = GameResource::where('game_idgame', $game->idgame)
            ->whereIn('type', [1, 2, 3])
            ->with(['values', 'characters'])
            ->orderByDesc('primaryORsecundary')
            ->orderBy('text_name')
            ->get();

        return view('combos.create', [
            'game' => $game,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
            'resources' => $resources,
            'defaults' => $this->defaultsFromChallenge($game, $resources, $request),
        ]);
    }

    /**
     * When arriving from the home page's daily challenge ("be the first to
     * submit one!"), `query`/`characterid` identify which CharacterQuery and
     * character the visitor was just shown, so the form can start pre-filled
     * with that query's own filters instead of leaving the visitor to guess
     * how to satisfy it. Absent those params (the normal "Submit a combo"
     * entry point), this returns no defaults and the form behaves as before.
     */
    private function defaultsFromChallenge(Game $game, iterable $resources, Request $request): array
    {
        $characterQuery = CharacterQuery::where('game_idgame', $game->idgame)
            ->where('idquery', $request->input('query'))
            ->first();

        if (! $characterQuery) {
            return [];
        }

        $filters = $characterQuery->filters ?? [];
        $defaults = [];

        if ($request->filled('characterid')) {
            $defaults['character_idcharacter'] = $request->integer('characterid');
        }

        if (($filters['listingtype'] ?? '-') !== '-' && ($filters['listingtype'] ?? '') !== '') {
            $defaults['listingtype'] = $filters['listingtype'];
        }

        // Only the "starts with" mode maps onto a single textarea value; for
        // contains/ends-with/not-contains the required text could belong
        // anywhere, so prefilling it as a literal prefix would mislead more
        // than it helps.
        if ((int) ($filters['combolike'] ?? 0) === 0 && ($filters['combo'] ?? '') !== '') {
            $defaults['combo'] = $filters['combo'];
        }

        if (($filters['damage'] ?? '') !== '') {
            $defaults['damage'] = $filters['damage'];
        }

        if (($filters['patch'] ?? '') !== '') {
            $defaults['patch'] = $filters['patch'];
        }

        $commentPieces = array_filter(explode('#', (string) ($filters['comments'] ?? '')));

        if ($commentPieces !== []) {
            $defaults['comments'] = implode(', ', $commentPieces);
        }

        if (! ($filters['novideo'] ?? false) && ($filters['video'] ?? '') !== '') {
            $defaults['video'] = $filters['video'];
        }

        foreach ($resources as $resource) {
            $field = str_replace(' ', '_', $resource->text_name);
            $value = $filters[$field] ?? null;

            if ($value === null || $value === '' || $value === '-') {
                continue;
            }

            $defaults['resources'][$resource->idgame_resources] = $value;
        }

        return $defaults;
    }

    public function store(StoreComboRequest $request, Game $game): RedirectResponse
    {
        $validated = $request->validated();

        $combo = $this->comboSubmission->create($game, [
            'combo' => $validated['combo'],
            'comments' => $validated['comments'] ?? null,
            'video' => $validated['video'] ?? null,
            'character_idcharacter' => $validated['character_idcharacter'],
            'damage' => $validated['damage'] ?? null,
            'type' => $validated['listingtype'],
            'patch' => $validated['patch'] ?? null,
        ], $validated['resources'] ?? [], auth()->id());

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
            ->whereIn('type', [1, 2, 3])
            ->with(['values', 'characters'])
            ->orderByDesc('primaryORsecundary')
            ->orderBy('text_name')
            ->get();

        $selectedResources = [];

        // A "Duplicated" (type 3) resource can have two rows for the same
        // game resource (e.g. two assists), so value_ids collects all of
        // them while value_id/number_value keep the first for types 1/2.
        foreach ($combo->resources as $resource) {
            $gameResourceId = $resource->resourceValue?->game_resources_idgame_resources;

            if ($gameResourceId === null) {
                continue;
            }

            $selectedResources[$gameResourceId]['value_id'] ??= $resource->Resources_values_idResources_values;
            $selectedResources[$gameResourceId]['number_value'] ??= $resource->number_value;
            $selectedResources[$gameResourceId]['value_ids'][] = $resource->Resources_values_idResources_values;
        }

        // If the combo already has a secondary resource value that's scoped
        // to characters not including its own, the toggle must start engaged
        // so editing the combo doesn't silently hide (and risk losing) it.
        $forceShowSecondaryResources = $resources
            ->where('primaryORsecundary', 0)
            ->whereIn('idgame_resources', array_keys($selectedResources))
            ->contains(fn (GameResource $resource) => $resource->characters->isNotEmpty()
                && ! $resource->characters->contains('idcharacter', $combo->character_idcharacter));

        return view('combos.edit', [
            'game' => $game,
            'combo' => $combo,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
            'resources' => $resources,
            'selectedResources' => $selectedResources,
            'forceShowSecondaryResources' => $forceShowSecondaryResources,
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
            $this->comboSubmission->syncResources($combo, $game, $validated['resources'] ?? []);
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
}
