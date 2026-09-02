<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CharacterQueryController extends Controller
{
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

            return redirect()->route('admin.queries.index', $game)->with('status', 'Saved.');
        }

        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->get();

        $attributes = [
            'label' => $validated['label'],
            'group_label' => $validated['group_label'] ?: null,
            'order' => $validated['order'] ?? 0,
            'filters' => $this->buildFilters($request, $primaryResources),
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

        return redirect()->route('admin.queries.index', $game)->with('status', 'Saved.');
    }

    /**
     * Build a FiltersCombos-compatible filter map (see
     * App\Http\Controllers\Concerns\FiltersCombos::applyFilters()) from the
     * submitted form: the fixed search fields plus, per primary GameResource,
     * its dynamic field(s) (text_name with spaces replaced by underscores —
     * see FiltersCombos::applyFilters() for the exact per-type shape).
     */
    private function buildFilters(Request $request, Collection $primaryResources): array
    {
        $filters = array_filter(
            $request->only(['combo', 'combolike', 'damage', 'patch', 'comments', 'notcomments', 'video', 'novideo', 'listingtype']),
            fn ($value) => $value !== null && $value !== ''
        );

        foreach ($primaryResources as $resource) {
            $field = str_replace(' ', '_', $resource->text_name);

            if ($resource->type === 3) {
                $values = array_values(array_filter(
                    (array) $request->input($field, []),
                    fn ($value) => $value !== null && $value !== '' && $value !== '-'
                ));

                if ($values !== []) {
                    $filters[$field] = $values;
                }

                continue;
            }

            $value = $request->input($field);

            if ($value === null || $value === '' || $value === '-') {
                continue;
            }

            $filters[$field] = $value;

            if ($resource->type === 2) {
                $compare = $request->input($field.'compare');

                if ($compare !== null && $compare !== '') {
                    $filters[$field.'compare'] = $compare;
                }
            }
        }

        return $filters;
    }
}
