<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Http\Requests\StoreComboRequest;
use App\Http\Requests\UpdateComboRequest;
use App\Models\Character;
use App\Models\CharacterButtonAlias;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GamePatch;
use App\Models\GameResource;
use App\Models\ListModel;
use App\Services\ComboNotationRenderer;
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

    public function __construct(
        private ComboSubmissionService $comboSubmission,
        private ComboNotationRenderer $comboNotationRenderer,
    ) {}

    /**
     * Browse/search combos for a game. Mirrors legacy's forms.php (search
     * mode) + submit.php (result query) combined into one page: the filter
     * form and its results live together instead of being two separate
     * pages joined by a GET redirect.
     *
     * A request with no query string at all (the bare "Advanced Search"
     * link) just shows the empty filter form — running an implicit
     * "show everything" query would look like a search already happened.
     * Anything else — an actual filter, a "view all" link that adds its own
     * marker (e.g. ?search=1), or a pagination link carrying the previous
     * query string forward — runs the search as before.
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

        $secondaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 0)
            ->with('values')
            ->orderBy('text_name')
            ->get();

        $combos = null;

        if ($request->query() !== []) {
            $query = Combo::query()
                ->with(['character', 'listingType', 'resources.resourceValue.characterAliases', 'user'])
                ->whereHas('character', fn (Builder $q) => $q->where('game_idgame', $game->idgame))
                ->visibleTo(auth()->user());

            $this->applyFilters($query, $request, $primaryResources->concat($secondaryResources), $game);

            $this->applyOrdering($query, $request);

            $combos = $query->paginate(20)->withQueryString();
        }

        return view('combos.index', [
            'game' => $game,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
            'primaryResources' => $primaryResources,
            'secondaryResources' => $secondaryResources,
            'combos' => $combos,
        ]);
    }

    public function show(Combo $combo): View
    {
        $combo->load(['character.game', 'listingType', 'patch', 'resources.resourceValue.gameResource', 'resources.resourceValue.characterAliases', 'user', 'verifier']);
        $combo->increment('views');

        $game = $combo->character->game;

        $primaryResources = $combo->resources
            ->filter(fn ($resource) => $resource->resourceValue?->gameResource?->primaryORsecundary === 1);

        $secondaryResources = $combo->resources
            ->filter(fn ($resource) => $resource->resourceValue?->gameResource?->primaryORsecundary === 0);

        $similarCombos = $this->similarCombos($combo);

        // Only the current value (plus the one before it, to know whether to
        // draw a nerf/buff arrow on it) is loaded here — older patches are
        // fetched from damageHistory() on demand, so a visitor who never
        // expands "Damage history" never costs a query for data they don't
        // look at.
        $recentDamageHistory = $combo->damageHistories()
            ->join('game_patches', 'game_patches.idgame_patch', '=', 'combo_damage_histories.patch_idgame_patch')
            // released_at alone can tie (same-day patches); idgame_patch as a
            // secondary key keeps the order deterministic and matching the
            // order patches were actually added in.
            ->orderByDesc('game_patches.released_at')
            ->orderByDesc('game_patches.idgame_patch')
            ->select('combo_damage_histories.*')
            ->with('patch')
            ->limit(2)
            ->get();

        $latestDamageHistory = $recentDamageHistory->first();
        $previousDamageHistory = $recentDamageHistory->get(1);
        $hasOlderDamageHistory = $recentDamageHistory->count() > 1;

        $dealiasedNotation = $this->comboNotationRenderer->resolveAliases($game, $combo->combo, $combo->character);

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
            'dealiasedNotation' => $dealiasedNotation,
            'latestDamageHistory' => $latestDamageHistory,
            'previousDamageHistory' => $previousDamageHistory,
            'hasOlderDamageHistory' => $hasOlderDamageHistory,
        ]);
    }

    /**
     * Older damage-history entries, loaded on demand when a visitor expands
     * the "Damage history" section on the combo page (see show()) instead of
     * on every page view.
     */
    public function damageHistory(Combo $combo): View
    {
        $olderDamageHistory = $combo->damageHistories()
            ->with('patch')
            ->get()
            // released_at alone can tie (same-day patches); idgame_patch as a
            // secondary key keeps this in the same order show() uses to pick
            // the latest/previous entries.
            ->sort(fn ($a, $b) => $a->patch->released_at <=> $b->patch->released_at ?: $a->patch->idgame_patch <=> $b->patch->idgame_patch)
            ->values()
            ->slice(0, -1)
            ->values();

        return view('combos.partials.damage-history-older', [
            'olderDamageHistory' => $olderDamageHistory,
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
            ->with(['values.characterAliases', 'characters'])
            ->orderByDesc('primaryORsecundary')
            ->orderBy('text_name')
            ->get();

        return view('combos.create', [
            'game' => $game,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
            'resources' => $resources,
            'resourceValueAliases' => $this->resourceValueAliasesByCharacter($resources),
            'characterButtonAliases' => $this->characterButtonAliasesByCharacter($game),
            'defaults' => $this->defaultsFromChallenge($game, $resources, $request),
        ]);
    }

    /**
     * The character select on the create form is client-side only (see
     * resourceValueAliasesByCharacter() above), so a character-specific move
     * alias (e.g. "Tackle" for one character's 236A) can't be filtered into
     * the "Other button names…" list server-side. Instead this ships every
     * character's move aliases as {characterId: [{alias, buttonName,
     * color}]} for app.js to render as buttons on change.
     */
    private function characterButtonAliasesByCharacter(Game $game): array
    {
        $aliases = [];

        $characterButtonAliases = CharacterButtonAlias::whereHas('character', fn (Builder $q) => $q->where('game_idgame', $game->idgame))
            ->with('button:idbutton,name,color')
            ->orderBy('alias')
            ->get();

        foreach ($characterButtonAliases as $alias) {
            $aliases[$alias->character_idcharacter][] = [
                'alias' => $alias->alias,
                'buttonName' => $alias->button->name,
                'color' => $alias->button->color,
            ];
        }

        return $aliases;
    }

    /**
     * The character select on the create form is client-side only (no
     * server round-trip on change — see filterSecondaryResources() in
     * app.js), so the primary/secondary "List"/"Duplicated" resource
     * <option> labels can't be rendered per-character server-side. Instead
     * this ships every character's alias overrides as {characterId: {
     * resourceValueId: aliasText }} for app.js to swap in on change.
     */
    private function resourceValueAliasesByCharacter(iterable $resources): array
    {
        $aliases = [];

        foreach ($resources as $resource) {
            if (! in_array($resource->type, [1, 3], true)) {
                continue;
            }

            foreach ($resource->values as $value) {
                foreach ($value->characterAliases as $alias) {
                    $aliases[$alias->character_idcharacter][$value->idResources_values] = $alias->alias;
                }
            }
        }

        return $aliases;
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
            $defaults['patch_idgame_patch'] = GamePatch::where('game_idgame', $game->idgame)
                ->where('label', $filters['patch'])
                ->value('idgame_patch');
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
            'patch_idgame_patch' => $validated['patch_idgame_patch'] ?? null,
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
            ->with(['values.characterAliases', 'characters'])
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

        $history = $combo->editHistories()->with('user')->limit(20)->get();

        return view('combos.edit', [
            'game' => $game,
            'combo' => $combo,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
            'resources' => $resources,
            'resourceValueAliases' => $this->resourceValueAliasesByCharacter($resources),
            'selectedResources' => $selectedResources,
            'forceShowSecondaryResources' => $forceShowSecondaryResources,
            'history' => $history,
        ]);
    }

    public function update(UpdateComboRequest $request, Combo $combo): RedirectResponse
    {
        $validated = $request->validated();
        $game = $combo->character->game;

        $combo->update([
            'combo' => $validated['combo'],
            'comments' => $validated['comments'] ?? null,
            'video' => $validated['video'] ?? null,
            'character_idcharacter' => $validated['character_idcharacter'],
            'damage' => $validated['damage'] ?? null,
            'type' => $validated['listingtype'],
            'patch_idgame_patch' => $validated['patch_idgame_patch'] ?? null,
        ]);
        $combo->recordEdit();

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

        $combo->recordEdit('deleted');
        $combo->delete();

        return redirect()->route('games.combos.index', ['game' => $game, 'search' => 1])->with('status', 'Combo deleted.');
    }
}
