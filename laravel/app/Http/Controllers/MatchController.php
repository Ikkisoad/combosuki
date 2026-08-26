<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMatchRequest;
use App\Http\Requests\UpdateMatchRequest;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameResource;
use App\Models\MatchResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function index(Game $game, Request $request): View
    {
        abort_unless($game->matches_enabled, 404);

        $query = GameMatch::where('game_idgame', $game->idgame)
            ->with(['playerOneCharacter', 'playerTwoCharacter', 'playerOneUser', 'playerTwoUser', 'user', 'resources.resourceValue', 'resources.gameResource']);

        $matchResources = $this->matchResourcesFor($game);

        $this->applyMatchFilters($query, $request, $matchResources);

        $matches = $query->orderByDesc('played_at')
            ->paginate(20)
            ->withQueryString();

        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        return view('matches.index', [
            'game' => $game,
            'matches' => $matches,
            'characters' => $characters,
            'matchResources' => $matchResources,
            'characterPickCounts' => $this->characterPickCounts($game, $characters),
            'topMatchups' => $this->topMatchups($game, $characters),
            'characterResourceUsage' => $this->characterResourceUsage($game, $characters, $matchResources),
        ]);
    }

    /**
     * How many matches each character appears in, on either side, across
     * the game's full match history (not affected by the index filters).
     */
    private function characterPickCounts(Game $game, Collection $characters): Collection
    {
        $counts = array_fill_keys($characters->pluck('idcharacter')->all(), 0);

        foreach (['player_one_character_idcharacter', 'player_two_character_idcharacter'] as $column) {
            GameMatch::where('game_idgame', $game->idgame)
                ->selectRaw("{$column} as character_id, COUNT(*) as picks")
                ->groupBy($column)
                ->pluck('picks', 'character_id')
                ->each(function ($picks, $characterId) use (&$counts) {
                    $counts[$characterId] = ($counts[$characterId] ?? 0) + $picks;
                });
        }

        return $characters
            ->map(fn (Character $character) => [
                'character' => $character,
                'picks' => $counts[$character->idcharacter] ?? 0,
            ])
            ->sortByDesc('picks')
            ->values();
    }

    /**
     * The most frequently played character-vs-character pairings (order
     * ignored, so "A vs B" and "B vs A" are counted together).
     */
    private function topMatchups(Game $game, Collection $characters, int $limit = 10): Collection
    {
        $charactersById = $characters->keyBy('idcharacter');

        return GameMatch::where('game_idgame', $game->idgame)
            ->get(['player_one_character_idcharacter', 'player_two_character_idcharacter'])
            ->map(function (GameMatch $match) {
                $pair = [$match->player_one_character_idcharacter, $match->player_two_character_idcharacter];
                sort($pair);

                return implode('-', $pair);
            })
            ->countBy()
            ->sortDesc()
            ->take($limit)
            ->map(function ($count, $key) use ($charactersById) {
                [$idA, $idB] = explode('-', $key);

                return [
                    'characterA' => $charactersById->get($idA),
                    'characterB' => $charactersById->get($idB),
                    'count' => $count,
                ];
            })
            ->values();
    }

    /**
     * For each character and each match-tracked resource, the value used
     * most often when that character was played (e.g. which "shell"/stance
     * a character is usually played with). Empty when the game has no
     * resources configured for match tracking.
     */
    private function characterResourceUsage(Game $game, Collection $characters, Collection $matchResources): Collection
    {
        if ($matchResources->isEmpty()) {
            return collect();
        }

        $charactersById = $characters->keyBy('idcharacter');
        $resourcesById = $matchResources->keyBy('idgame_resources');
        $valuesById = $matchResources->flatMap(fn (GameResource $resource) => $resource->values)->keyBy('idResources_values');

        $rows = DB::table('match_resources')
            ->join('matches', 'match_resources.match_idmatch', '=', 'matches.idmatch')
            ->where('matches.game_idgame', $game->idgame)
            ->selectRaw('CASE match_resources.player WHEN 1 THEN matches.player_one_character_idcharacter ELSE matches.player_two_character_idcharacter END as character_id, match_resources.game_resources_idgame_resources as resource_id, match_resources.resources_values_idResources_values as value_id, COUNT(*) as uses')
            ->groupBy('character_id', 'resource_id', 'value_id')
            ->get();

        return $rows
            ->groupBy(fn ($row) => $row->character_id.'-'.$row->resource_id)
            ->map(fn ($group) => $group->sortByDesc('uses')->first())
            ->map(function ($row) use ($charactersById, $resourcesById, $valuesById) {
                return [
                    'character' => $charactersById->get($row->character_id),
                    'resource' => $resourcesById->get($row->resource_id),
                    'value' => $valuesById->get($row->value_id),
                    'uses' => $row->uses,
                ];
            })
            ->filter(fn (array $entry) => $entry['character'] && $entry['resource'] && $entry['value'])
            ->sortBy(fn (array $entry) => $entry['character']->name)
            ->values();
    }

    private function matchResourcesFor(Game $game)
    {
        return GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->where('include_in_matches', true)
            ->with('values')
            ->orderBy('text_name')
            ->get();
    }

    private function applyMatchFilters(Builder $query, Request $request, $matchResources): void
    {
        $characterA = $request->filled('character_a') && $request->input('character_a') !== '-'
            ? $request->integer('character_a')
            : null;
        $characterB = $request->filled('character_b') && $request->input('character_b') !== '-'
            ? $request->integer('character_b')
            : null;

        if ($characterA !== null && $characterB !== null) {
            $query->where(function (Builder $q) use ($characterA, $characterB) {
                $q->where(fn (Builder $q2) => $q2->where('player_one_character_idcharacter', $characterA)
                    ->where('player_two_character_idcharacter', $characterB))
                    ->orWhere(fn (Builder $q2) => $q2->where('player_one_character_idcharacter', $characterB)
                        ->where('player_two_character_idcharacter', $characterA));
            });
        } elseif ($characterA !== null || $characterB !== null) {
            $character = $characterA ?? $characterB;

            $query->where(fn (Builder $q) => $q->where('player_one_character_idcharacter', $character)
                ->orWhere('player_two_character_idcharacter', $character));
        }

        if ($request->filled('player')) {
            $player = '%'.$request->string('player').'%';

            $query->where(fn (Builder $q) => $q->where('player_one', 'like', $player)
                ->orWhere('player_two', 'like', $player));
        }

        if ($request->filled('date_from')) {
            $query->where('played_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('played_at', '<=', $request->string('date_to'));
        }

        if ($request->filled('video')) {
            $query->where('video', 'like', '%'.$request->string('video').'%');
        }

        foreach ($matchResources as $resource) {
            $fieldA = "resource_{$resource->idgame_resources}_a";
            $fieldB = "resource_{$resource->idgame_resources}_b";

            $valueA = $request->filled($fieldA) && $request->input($fieldA) !== '-'
                ? $request->integer($fieldA)
                : null;
            $valueB = $request->filled($fieldB) && $request->input($fieldB) !== '-'
                ? $request->integer($fieldB)
                : null;

            if ($valueA === null && $valueB === null) {
                continue;
            }

            $hasPlayerValue = fn (Builder $q, int $player, int $value) => $q->whereHas('resources', fn (Builder $r) => $r
                ->where('game_resources_idgame_resources', $resource->idgame_resources)
                ->where('resources_values_idResources_values', $value)
                ->where('player', $player));

            if ($valueA !== null && $valueB !== null) {
                $query->where(function (Builder $q) use ($hasPlayerValue, $valueA, $valueB) {
                    $q->where(fn (Builder $q2) => $hasPlayerValue($hasPlayerValue($q2, 1, $valueA), 2, $valueB))
                        ->orWhere(fn (Builder $q2) => $hasPlayerValue($hasPlayerValue($q2, 1, $valueB), 2, $valueA));
                });
            } else {
                $value = $valueA ?? $valueB;

                $query->whereHas('resources', fn (Builder $r) => $r
                    ->where('game_resources_idgame_resources', $resource->idgame_resources)
                    ->where('resources_values_idResources_values', $value));
            }
        }
    }

    public function create(Game $game): View
    {
        abort_unless($game->matches_enabled, 404);

        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        return view('matches.create', [
            'game' => $game,
            'characters' => $characters,
            'matchResources' => $this->matchResourcesFor($game),
        ]);
    }

    public function store(StoreMatchRequest $request, Game $game): RedirectResponse
    {
        $validated = $request->validated();

        $match = GameMatch::create([
            'game_idgame' => $game->idgame,
            'player_one' => $validated['player_one'],
            'player_one_user_iduser' => $validated['player_one_user_iduser'] ?? null,
            'player_one_character_idcharacter' => $validated['player_one_character_idcharacter'],
            'player_two' => $validated['player_two'],
            'player_two_user_iduser' => $validated['player_two_user_iduser'] ?? null,
            'player_two_character_idcharacter' => $validated['player_two_character_idcharacter'],
            'video' => $validated['video'],
            'played_at' => $validated['played_at'],
            'user_iduser' => auth()->id(),
        ]);

        $this->syncMatchResources($match, $game, $validated);

        return redirect()->route('games.matches.index', $game)->with('status', 'Match submitted.');
    }

    public function edit(GameMatch $gameMatch): View
    {
        $this->authorize('update', $gameMatch);

        $gameMatch->load(['playerOneUser', 'playerTwoUser', 'resources']);
        $game = $gameMatch->game;

        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        return view('matches.edit', [
            'game' => $game,
            'match' => $gameMatch,
            'characters' => $characters,
            'matchResources' => $this->matchResourcesFor($game),
        ]);
    }

    public function update(UpdateMatchRequest $request, GameMatch $gameMatch): RedirectResponse
    {
        $validated = $request->validated();

        $gameMatch->update([
            'player_one' => $validated['player_one'],
            'player_one_user_iduser' => $validated['player_one_user_iduser'] ?? null,
            'player_one_character_idcharacter' => $validated['player_one_character_idcharacter'],
            'player_two' => $validated['player_two'],
            'player_two_user_iduser' => $validated['player_two_user_iduser'] ?? null,
            'player_two_character_idcharacter' => $validated['player_two_character_idcharacter'],
            'video' => $validated['video'],
            'played_at' => $validated['played_at'],
        ]);

        $this->syncMatchResources($gameMatch, $gameMatch->game, $validated);

        return redirect()->route('games.matches.index', $gameMatch->game)->with('status', 'Match updated.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncMatchResources(GameMatch $match, Game $game, array $validated): void
    {
        foreach ($this->matchResourcesFor($game) as $resource) {
            foreach ([1 => 'player_one_resources', 2 => 'player_two_resources'] as $player => $field) {
                $value = $validated[$field][$resource->idgame_resources] ?? null;

                if ($value === null || $value === '') {
                    MatchResource::where('match_idmatch', $match->idmatch)
                        ->where('game_resources_idgame_resources', $resource->idgame_resources)
                        ->where('player', $player)
                        ->delete();

                    continue;
                }

                MatchResource::updateOrCreate(
                    [
                        'match_idmatch' => $match->idmatch,
                        'game_resources_idgame_resources' => $resource->idgame_resources,
                        'player' => $player,
                    ],
                    ['resources_values_idResources_values' => $value]
                );
            }
        }
    }

    public function destroy(GameMatch $gameMatch): RedirectResponse
    {
        $this->authorize('delete', $gameMatch);

        $game = $gameMatch->game;
        $gameMatch->delete();

        return redirect()->route('games.matches.index', $game)->with('status', 'Match deleted.');
    }
}
