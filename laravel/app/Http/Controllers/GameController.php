<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Models\Button;
use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameMatch;
use App\Models\GamePatch;
use App\Models\GameResource;
use App\Models\ListModel;
use App\Models\ResourceValue;
use App\Services\TierListAggregator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GameController extends Controller
{
    use FiltersCombos;

    private const DEFAULT_BUTTONS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '214', '236', 'A', 'B', 'C', 'j', '>'];

    public function index(): View
    {
        $viewer = auth()->user();
        $moderatedGameIds = $viewer?->moderatedGames()->pluck('game.idgame')->all() ?? [];

        $games = Game::withCount([
            'combos',
            'combos as unverified_combos_count' => fn ($query) => $query
                ->whereNotNull('user_iduser')
                ->where(fn ($q) => $q->whereNull('verified')->orWhere('verified', 0)),
        ])
            ->orderBy('name')
            ->get()
            ->each(function (Game $game) use ($viewer, $moderatedGameIds) {
                // isTrusted() is intentionally not used here: it also returns
                // true for a bare is_moderator, which would wrongly grant the
                // "every game" highlight to a moderator who should only see
                // it on games they specifically moderate (the pivot check).
                $game->show_unverified_highlight = $game->unverified_combos_count > 0
                    && $viewer !== null
                    && ($viewer->is_admin || $viewer->trusted_user || in_array($game->idgame, $moderatedGameIds, true));
            });

        return view('games.index', ['games' => $games]);
    }

    public function create(): View
    {
        return view('games.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'image' => ['required', 'string', 'max:255'],
        ]);

        $game = DB::transaction(function () use ($validated, $request) {
            $game = Game::create([
                'name' => $validated['name'],
                'image' => $validated['image'],
                'complete' => 0,
                // Legacy per-game password gating was replaced by user auth
                // (see AnonymousWriteAccessTest); this column is only kept
                // NOT NULL by the schema, so it's never surfaced or checked.
                'modPass' => bcrypt(Str::random(32)),
            ]);

            // Game editing is scoped per-game (see GamePolicy), so the
            // creator needs an explicit assignment to keep editing their own
            // creation afterwards.
            $game->moderators()->attach($request->user()->iduser);

            foreach (self::DEFAULT_BUTTONS as $order => $name) {
                Button::create([
                    'name' => $name,
                    'game_idgame' => $game->idgame,
                    'order' => $order,
                    'ignored' => $name === '>',
                ]);
            }

            Character::create(['name' => 'Combo Chan', 'game_idgame' => $game->idgame]);

            $whereResource = GameResource::create([
                'game_idgame' => $game->idgame,
                'text_name' => 'Where?',
                'type' => 1,
                'primaryORsecundary' => 1,
            ]);
            ResourceValue::create(['value' => 'Midscreen', 'order' => 0, 'game_resources_idgame_resources' => $whereResource->idgame_resources]);
            ResourceValue::create(['value' => 'Corner', 'order' => 1, 'game_resources_idgame_resources' => $whereResource->idgame_resources]);

            GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => -1]);
            GameEntry::create(['title' => 'Okizeme', 'gameid' => $game->idgame]);
            GameEntry::create(['title' => 'Mix Up', 'gameid' => $game->idgame]);

            return $game;
        });

        return redirect()->route('admin.game.edit', $game)->with('status', 'Game created! Finish setting it up below.');
    }

    public function show(Game $game, Request $request): View
    {
        $game->load(['links' => fn ($query) => $query->orderBy('Title')]);
        $game->increment('views');

        $characters = Character::where('game_idgame', $game->idgame)
            ->withCount('combos')
            ->orderByDesc('combos_count')
            ->orderBy('name')
            ->get();

        $latestCombos = Combo::with(['character', 'listingType', 'user'])
            ->whereHas('character', fn ($query) => $query->where('game_idgame', $game->idgame))
            ->visibleTo(auth()->user())
            ->orderByDesc('submited')
            ->limit(5)
            ->get();

        $listingTypes = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();

        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->with('values')
            ->orderBy('text_name')
            ->get();

        return view('games.show', [
            'game' => $game,
            'characters' => $characters,
            'latestCombos' => $latestCombos,
            'listingTypes' => $listingTypes,
            'primaryResources' => $primaryResources,
            'patches' => $game->patches,
            'selectedTierPatch' => $request->input('tier_patch') ?? $game->currentPatch?->idgame_patch ?? 'all',
        ]);
    }

    public function guidesTab(Game $game): View
    {
        $featuredGuides = ListModel::where('game_idgame', $game->idgame)
            ->where('type', 3)
            ->with('user')
            ->orderByDesc('idlist')
            ->limit(10)
            ->get();

        $guides = ListModel::where('game_idgame', $game->idgame)
            ->where('type', '!=', 3)
            ->with('user')
            ->orderByDesc('type')
            ->orderByDesc('idlist')
            ->limit(10)
            ->get();

        return view('games.partials.guides-tab', ['game' => $game, 'guides' => $guides, 'featuredGuides' => $featuredGuides]);
    }

    public function mostViewedTab(Game $game): View
    {
        $mostViewedCombos = Combo::with(['character', 'listingType', 'user'])
            ->whereHas('character', fn ($query) => $query->where('game_idgame', $game->idgame))
            ->visibleTo(auth()->user())
            ->orderByDesc('views')
            ->limit(3)
            ->get();

        $mostViewedGuides = ListModel::where('game_idgame', $game->idgame)
            ->with('user')
            ->orderByDesc('views')
            ->limit(3)
            ->get();

        return view('games.partials.most-viewed-tab', [
            'game' => $game,
            'mostViewedCombos' => $mostViewedCombos,
            'mostViewedGuides' => $mostViewedGuides,
        ]);
    }

    /**
     * Average, per character, the damage of that character's top result for
     * each of the game's default queries (character_default_queries — see
     * CharacterQuery/FiltersCombos::searchCombos()), the same "top combo per
     * default query" data the character page shows. Surfaces the game-wide
     * average of those per-character averages, the character with the
     * highest one, and the full per-character breakdown (sorted desc) for
     * the comparison graph — plus, per query, the average damage across
     * characters and the character with the highest damage for that
     * specific query, so each default query can get its own tab.
     */
    public function damageStatsTab(Game $game): View
    {
        $queries = CharacterQuery::where('game_idgame', $game->idgame)
            ->orderBy('order')
            ->orderBy('label')
            ->get();

        $characters = Character::where('game_idgame', $game->idgame)->get();

        // idquery => (idcharacter => top damage for that query, or null)
        $damageMatrix = $queries->mapWithKeys(function (CharacterQuery $query) use ($game, $characters) {
            $perCharacter = $characters->mapWithKeys(function (Character $character) use ($game, $query) {
                $damage = $this->searchCombos(
                    $game,
                    array_merge($query->filters ?? [], ['characterid' => $character->idcharacter]),
                    1
                )->first()?->damage;

                return [$character->idcharacter => $damage !== null ? (float) $damage : null];
            });

            return [$query->idquery => $perCharacter];
        });

        $allCharacterAverages = $characters
            ->map(function (Character $character) use ($queries, $damageMatrix) {
                $damages = $queries
                    ->map(fn (CharacterQuery $query) => $damageMatrix[$query->idquery][$character->idcharacter])
                    ->filter(fn ($damage) => $damage !== null);

                return [
                    'character' => $character,
                    'average' => $damages->isNotEmpty() ? $damages->avg() : null,
                ];
            });

        $charactersWithData = $allCharacterAverages
            ->filter(fn (array $entry) => $entry['average'] !== null)
            ->sortByDesc('average')
            ->values();

        $charactersWithoutData = $allCharacterAverages
            ->filter(fn (array $entry) => $entry['average'] === null)
            ->sortBy(fn (array $entry) => $entry['character']->name)
            ->values();

        $characterAverages = $charactersWithData->concat($charactersWithoutData)->values();

        $gameAverageDamage = $charactersWithData->isNotEmpty() ? $charactersWithData->avg('average') : null;
        $topCharacterEntry = $charactersWithData->first();

        $queryStats = $queries->map(function (CharacterQuery $query) use ($characters, $damageMatrix) {
            $entries = $characters->map(fn (Character $character) => [
                'character' => $character,
                'damage' => $damageMatrix[$query->idquery][$character->idcharacter],
            ]);

            $entriesWithData = $entries->filter(fn (array $entry) => $entry['damage'] !== null)->sortByDesc('damage')->values();
            $entriesWithoutData = $entries->filter(fn (array $entry) => $entry['damage'] === null)->sortBy(fn (array $entry) => $entry['character']->name)->values();

            return [
                'query' => $query,
                'average' => $entriesWithData->isNotEmpty() ? $entriesWithData->avg('damage') : null,
                'topEntry' => $entriesWithData->first(),
                'characterDamages' => $entriesWithData->concat($entriesWithoutData)->values(),
            ];
        });

        return view('games.partials.damage-stats-tab', [
            'game' => $game,
            'queriesCount' => $queries->count(),
            'gameAverageDamage' => $gameAverageDamage,
            'topCharacterEntry' => $topCharacterEntry,
            'characterAverages' => $characterAverages,
            'queryStats' => $queryStats,
        ]);
    }

    public function matchesTab(Game $game): View
    {
        $latestMatches = collect();

        if ($game->matches_enabled) {
            $latestMatches = GameMatch::where('game_idgame', $game->idgame)
                ->with(['playerOneCharacter', 'playerTwoCharacter', 'playerOneUser', 'playerTwoUser'])
                ->orderByDesc('played_at')
                ->limit(5)
                ->get();
        }

        return view('games.partials.matches-tab', ['game' => $game, 'latestMatches' => $latestMatches]);
    }

    public function tierListsTab(Game $game, Request $request, TierListAggregator $tierListAggregator): View
    {
        [$tierFrom, $tierTo] = $this->resolveTierPatchWindow($game, $request);

        $tierListAggregate = $tierListAggregator->aggregate($game, $tierFrom, $tierTo);

        return view('games.partials.tier-lists-tab', ['tierListAggregate' => $tierListAggregate]);
    }

    /**
     * Translates the tier-list tab's patch selection into a date range for
     * TierListAggregator, which still only understands from/to dates (tier
     * lists carry no patch reference of their own — see the "Rework patch
     * into a dated patch list" plan). No `tier_patch` param at all (the
     * initial AJAX tab load) defaults to the game's current patch's window,
     * so opening the tab shows "now" rather than every tier list ever
     * submitted; `tier_patch=all` explicitly clears back to unrestricted.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveTierPatchWindow(Game $game, Request $request): array
    {
        $param = $request->input('tier_patch');

        if ($param === 'all') {
            return [null, null];
        }

        if ($param !== null && $param !== '') {
            $patch = GamePatch::where('game_idgame', $game->idgame)->find($param);

            if ($patch) {
                return [$patch->released_at, $patch->ended_at?->copy()->subDay()];
            }
        }

        $current = $game->currentPatch;

        return $current ? [$current->released_at, null] : [null, null];
    }
}
