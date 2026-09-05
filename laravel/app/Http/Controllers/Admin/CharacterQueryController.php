<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Support\ChallengeStatsCache;
use App\Support\DamageStatsCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CharacterQueryController extends Controller
{
    use FiltersCombos;

    public function index(Game $game): View
    {
        $queries = CharacterQuery::where('game_idgame', $game->idgame)
            ->with('characters')
            ->orderBy('order')
            ->orderBy('label')
            ->get();

        $buttons = $game->buttons()->orderBy('order')->get();

        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        $listingTypes = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();

        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->with('values')
            ->orderBy('text_name')
            ->get();

        $groupLabels = $queries->pluck('group_label')->filter()->unique()->sort()->values();

        return view('admin.queries.index', [
            'game' => $game,
            'queries' => $queries,
            'buttons' => $buttons,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
            'primaryResources' => $primaryResources,
            'groupLabels' => $groupLabels,
        ]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,Update,Delete'],
            'label' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:150'],
            'group_label' => ['nullable', 'string', 'max:150'],
            'character_idcharacters' => ['nullable', 'array'],
            'character_idcharacters.*' => [
                'integer',
                Rule::exists('character', 'idcharacter')->where('game_idgame', $game->idgame),
            ],
            'order' => ['nullable', 'integer'],
            'idquery' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        if ($validated['action'] === 'Delete') {
            CharacterQuery::where('idquery', $validated['idquery'])
                ->where('game_idgame', $game->idgame)
                ->delete();

            $this->invalidateQueryDependentCaches($game->idgame);

            return redirect()->route('admin.queries.index', $game)->with('status', 'Saved.');
        }

        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->get();

        $attributes = [
            'label' => $validated['label'],
            'group_label' => $validated['group_label'] ?: null,
            'order' => $validated['order'] ?? 0,
            'filters' => $this->buildFiltersFromRequest($request, $primaryResources),
        ];

        $characterIds = $validated['character_idcharacters'] ?? [];

        if ($validated['action'] === 'Add') {
            $query = CharacterQuery::create([...$attributes, 'game_idgame' => $game->idgame]);
            $query->characters()->sync($characterIds);
        } else {
            $query = CharacterQuery::where('idquery', $validated['idquery'])
                ->where('game_idgame', $game->idgame)
                ->first();

            if ($query) {
                $query->update($attributes);
                $query->characters()->sync($characterIds);
            }
        }

        $this->invalidateQueryDependentCaches($game->idgame);

        return redirect()->route('admin.queries.index', $game)->with('status', 'Saved.');
    }

    /**
     * GameController::damageStatsTab() and ChallengeController's ranking/
     * calendar tabs both cache computations derived from a game's
     * CharacterQuery rows (see DamageStatsCache/ChallengeStatsCache) and are
     * normally invalidated by Combo::booted() — but a query's own filters,
     * character scope, or existence changes here, not through a Combo write,
     * so those caches need to be busted directly from every action this
     * method takes (Add/Update/Delete, including the query-builder Delete,
     * which fires no Eloquent model events at all).
     */
    private function invalidateQueryDependentCaches(int $gameId): void
    {
        DamageStatsCache::forget($gameId);
        ChallengeStatsCache::bumpVersion();
    }
}
