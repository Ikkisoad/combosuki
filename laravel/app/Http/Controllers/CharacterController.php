<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Services\ComboFlowChartBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CharacterController extends Controller
{
    use FiltersCombos;

    public function __construct(private ComboFlowChartBuilder $flowChartBuilder) {}

    public function show(Game $game, Character $character): View
    {
        $character->increment('views');
        $character->load('links');

        $queries = CharacterQuery::where('game_idgame', $game->idgame)
            ->where(fn ($query) => $query->doesntHave('characters')->orWhereHas(
                'characters',
                fn ($characters) => $characters->where('character.idcharacter', $character->idcharacter)
            ))
            ->orderBy('order')
            ->orderBy('label')
            ->get();

        $topCombos = $queries->mapWithKeys(function (CharacterQuery $query) use ($game, $character) {
            $filters = array_merge($query->filters ?? [], ['characterid' => $character->idcharacter]);

            return [$query->idquery => $this->searchCombos($game, $filters, 1)->first()];
        });

        $topDamageCombos = Combo::with('listingType')
            ->where('character_idcharacter', $character->idcharacter)
            ->visibleTo(auth()->user())
            ->orderByDesc('damage')
            ->limit(3)
            ->get();

        $averageDamage = Combo::where('character_idcharacter', $character->idcharacter)
            ->whereNotNull('damage')
            ->visibleTo(auth()->user())
            ->avg('damage');

        return view('characters.show', [
            'game' => $game,
            'character' => $character,
            'queries' => $queries,
            'topCombos' => $topCombos,
            'topDamageCombos' => $topDamageCombos,
            'averageDamage' => $averageDamage,
        ]);
    }

    public function flowChartTab(Game $game, Character $character, Request $request): View
    {
        [$listingTypes, $primaryResources] = $this->flowChartFilterOptions($game);
        $combos = $this->filteredCombos($game, $character, $request, $primaryResources);

        $starters = $this->flowChartBuilder->nextMoves($character, $combos, []);

        return view('characters.partials.flow-chart-tab', [
            'game' => $game,
            'character' => $character,
            'starters' => $starters,
            'listingTypes' => $listingTypes,
            'primaryResources' => $primaryResources,
            'filters' => $request->query(),
        ]);
    }

    public function flowChartNext(Game $game, Character $character, Request $request): JsonResponse
    {
        [, $primaryResources] = $this->flowChartFilterOptions($game);
        $combos = $this->filteredCombos($game, $character, $request, $primaryResources);

        $moves = $this->flowChartBuilder->nextMoves($character, $combos, (array) $request->input('path', []));

        return response()->json(['moves' => $moves]);
    }

    public function flowChartMatches(Game $game, Character $character, Request $request): View
    {
        [, $primaryResources] = $this->flowChartFilterOptions($game);
        $combos = $this->filteredCombos($game, $character, $request, $primaryResources);

        $matches = $this->flowChartBuilder
            ->matchingCombos($character, $combos, (array) $request->input('path', []))
            ->sortByDesc('damage')
            ->values();

        return view('characters.partials.flow-chart-matches', [
            'game' => $game,
            'character' => $character,
            'combos' => $matches,
        ]);
    }

    /**
     * The Type and primary-resource options the flow chart's filter form
     * offers, same source as the "Quick Search" form on the game page
     * (Type: every GameEntry for $game; primary resources: every
     * GameResource flagged primaryORsecundary for $game).
     */
    private function flowChartFilterOptions(Game $game): array
    {
        $listingTypes = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();

        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->with('values')
            ->orderBy('text_name')
            ->get();

        return [$listingTypes, $primaryResources];
    }

    /**
     * $character's combos visible to the current viewer, narrowed by
     * whatever Type/primary-resource filters are present on $request — via
     * the exact same FiltersCombos::applyFilters() every other combo
     * listing (search, damage stats, ...) uses, so the flow chart's filters
     * behave identically. Shared by flowChartTab() and flowChartMatches()
     * so a path search stays scoped to the same filtered set the chart
     * itself was built from.
     */
    private function filteredCombos(Game $game, Character $character, Request $request, Collection $primaryResources): Collection
    {
        // applyFilters() expects a genuine Eloquent Builder — $character->
        // combos() (a HasMany relation) forwards calls to one via __call()
        // but isn't type-compatible with the strict Builder type hint.
        $query = Combo::where('character_idcharacter', $character->idcharacter)->visibleTo(auth()->user());

        $this->applyFilters($query, $request, $primaryResources, $game);

        return $query->get();
    }
}
