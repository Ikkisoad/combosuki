<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\CharacterResourceValueAlias;
use App\Models\Game;
use App\Models\GameResource;
use App\Models\ResourceValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class GameResourceController extends Controller
{
    public function index(Game $game): View
    {
        $resources = GameResource::where('game_idgame', $game->idgame)
            ->with('characters')
            ->orderByDesc('primaryORsecundary')
            ->orderBy('text_name')
            ->get();

        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        return view('admin.resources.index', ['game' => $game, 'resources' => $resources, 'characters' => $characters]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,SaveAll,Delete'],
            'resource' => ['required_if:action,Add', 'nullable', 'string', 'max:45'],
            'type' => ['required_if:action,Add', 'nullable', 'integer', 'in:1,2,3'],
            'primaryORsecundary' => ['nullable', 'integer', 'in:0,1'],
            'primaryorsecundary' => ['nullable', 'integer', 'in:0,1'],
            'include_in_matches' => ['nullable', 'boolean'],
            'include_in_tier_lists' => ['nullable', 'boolean'],
            'characters' => ['array'],
            'characters.*' => ['integer', 'exists:character,idcharacter,game_idgame,'.$game->idgame],
            'idresource' => ['required_if:action,Delete', 'nullable', 'integer'],
            'resources' => ['required_if:action,SaveAll', 'array'],
            'resources.*.resource' => ['required', 'string', 'max:45'],
            'resources.*.type' => ['required', 'integer', 'in:1,2,3'],
            'resources.*.primaryORsecundary' => ['nullable', 'integer', 'in:0,1'],
            'resources.*.include_in_matches' => ['nullable', 'boolean'],
            'resources.*.include_in_tier_lists' => ['nullable', 'boolean'],
            'resources.*.characters' => ['array'],
            'resources.*.characters.*' => ['integer', 'exists:character,idcharacter,game_idgame,'.$game->idgame],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        if ($validated['action'] === 'Add') {
            $primary = $validated['primaryORsecundary'] ?? $validated['primaryorsecundary'] ?? 0;
            $includeInTierLists = $primary == 1 && $request->boolean('include_in_tier_lists');

            if ($includeInTierLists) {
                GameResource::where('game_idgame', $game->idgame)
                    ->where('include_in_tier_lists', true)
                    ->update(['include_in_tier_lists' => false]);
            }

            $gameResource = GameResource::create([
                'game_idgame' => $game->idgame,
                'text_name' => $validated['resource'],
                'type' => $validated['type'],
                'primaryORsecundary' => $primary,
                'include_in_matches' => $primary == 1 && $request->boolean('include_in_matches'),
                'include_in_tier_lists' => $includeInTierLists,
            ]);

            $gameResource->characters()->sync($validated['characters'] ?? []);
        } elseif ($validated['action'] === 'SaveAll') {
            $gameResources = GameResource::where('game_idgame', $game->idgame)
                ->whereIn('idgame_resources', array_keys($validated['resources']))
                ->get()
                ->keyBy('idgame_resources');

            // Only one resource per game may be flagged for tier lists — if
            // more than one is checked in this submission, the last one in
            // submission order wins.
            $tierListWinner = null;

            foreach ($validated['resources'] as $idResource => $data) {
                $primary = $data['primaryORsecundary'] ?? 0;

                if ($primary == 1 && (bool) ($data['include_in_tier_lists'] ?? false)) {
                    $tierListWinner = (int) $idResource;
                }
            }

            foreach ($validated['resources'] as $idResource => $data) {
                $gameResource = $gameResources->get((int) $idResource);

                if (! $gameResource) {
                    continue;
                }

                $primary = $data['primaryORsecundary'] ?? 0;

                $gameResource->update([
                    'text_name' => $data['resource'],
                    'type' => $data['type'],
                    'primaryORsecundary' => $primary,
                    'include_in_matches' => $primary == 1 && (bool) ($data['include_in_matches'] ?? false),
                    'include_in_tier_lists' => $tierListWinner === (int) $idResource,
                ]);

                $gameResource->characters()->sync($data['characters'] ?? []);
            }
        } else {
            $resource = GameResource::where('idgame_resources', $validated['idresource'])
                ->where('game_idgame', $game->idgame)
                ->first();

            $resource?->values->each(function ($value) {
                if ($value->icon) {
                    Storage::disk('public')->delete($value->icon);
                }

                $value->delete();
            });
            $resource?->delete();
        }

        return redirect()->route('admin.resources.index', $game)->with('status', 'Saved.');
    }

    public function values(Game $game, GameResource $resource): View
    {
        abort_if($resource->game_idgame !== $game->idgame, 404);

        $values = $resource->values()->orderBy('order')->orderBy('value')->get();

        return view('admin.resources.values', ['game' => $game, 'resource' => $resource, 'values' => $values]);
    }

    public function storeValue(Request $request, Game $game, GameResource $resource): RedirectResponse
    {
        abort_if($resource->game_idgame !== $game->idgame, 404);

        $rules = [
            'action' => ['required', 'in:EditAdd,EditUpdate,EditDelete'],
            'order' => ['nullable', 'numeric'],
            'icon' => ['nullable', 'image', 'max:5120'],
            'idresourcevalue' => ['required_if:action,EditUpdate,EditDelete', 'nullable', 'integer'],
        ];

        $rules['resourcevalue'] = $resource->type === 2
            ? ['required_if:action,EditAdd,EditUpdate', 'nullable', 'numeric']
            : ['required_if:action,EditAdd,EditUpdate', 'nullable', 'string', 'max:115'];

        $validated = $request->validate($rules);

        // TODO: record which user made this edit once an audit/edit-log exists
        if ($validated['action'] === 'EditAdd') {
            ResourceValue::create([
                'value' => $validated['resourcevalue'],
                'order' => $validated['order'] ?? null,
                'icon' => $request->hasFile('icon') ? $request->file('icon')->store('resource-value-icons', 'public') : null,
                'game_resources_idgame_resources' => $resource->idgame_resources,
            ]);
        } elseif ($validated['action'] === 'EditUpdate') {
            $value = ResourceValue::where('idResources_values', $validated['idresourcevalue'])
                ->where('game_resources_idgame_resources', $resource->idgame_resources)
                ->first();

            $attributes = ['value' => $validated['resourcevalue'], 'order' => $validated['order'] ?? null];

            if ($request->hasFile('icon')) {
                if ($value?->icon) {
                    Storage::disk('public')->delete($value->icon);
                }

                $attributes['icon'] = $request->file('icon')->store('resource-value-icons', 'public');
            }

            $value?->update($attributes);
        } else {
            $value = ResourceValue::where('idResources_values', $validated['idresourcevalue'])
                ->where('game_resources_idgame_resources', $resource->idgame_resources)
                ->first();

            if ($value?->icon) {
                Storage::disk('public')->delete($value->icon);
            }

            $value?->delete();
        }

        return redirect()->route('admin.resources.values', [$game, $resource])->with('status', 'Saved.');
    }

    /**
     * Per-character overrides of this resource's value display (alias text
     * and/or icon) — e.g. a "Support" resource whose values are 1/2/3 but
     * each character calls them something different. Only meaningful for
     * List-type resources, since Number/Duplicated values aren't the kind
     * of fixed, nameable options a character would rename.
     */
    public function aliases(Game $game, GameResource $resource): View
    {
        abort_if($resource->game_idgame !== $game->idgame, 404);
        abort_if($resource->type !== 1, 404);

        $values = $resource->values()->orderBy('order')->orderBy('value')->get();
        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        $aliases = CharacterResourceValueAlias::whereIn('resources_values_idResources_values', $values->pluck('idResources_values'))
            ->get()
            ->groupBy(fn ($row) => $row->character_idcharacter.'-'.$row->resources_values_idResources_values);

        return view('admin.resources.aliases', [
            'game' => $game,
            'resource' => $resource,
            'values' => $values,
            'characters' => $characters,
            'aliases' => $aliases,
        ]);
    }

    public function storeAliases(Request $request, Game $game, GameResource $resource): RedirectResponse
    {
        abort_if($resource->game_idgame !== $game->idgame, 404);
        abort_if($resource->type !== 1, 404);

        $valueIds = $resource->values()->pluck('idResources_values')->all();
        $characterIds = Character::where('game_idgame', $game->idgame)->pluck('idcharacter')->all();

        $request->validate([
            'aliases' => ['array'],
            'aliases.*.*.alias' => ['nullable', 'string', 'max:45'],
            'aliases.*.*.icon' => ['nullable', 'image', 'max:5120'],
        ]);

        $cells = $request->input('aliases', []);
        $icons = $request->file('aliases', []);

        $existing = CharacterResourceValueAlias::whereIn('resources_values_idResources_values', $valueIds)
            ->whereIn('character_idcharacter', $characterIds)
            ->get()
            ->keyBy(fn ($row) => $row->character_idcharacter.'-'.$row->resources_values_idResources_values);

        // TODO: record which user made this edit once an audit/edit-log exists
        foreach ($characterIds as $characterId) {
            foreach ($valueIds as $valueId) {
                $row = $existing->get($characterId.'-'.$valueId);
                $aliasText = trim((string) ($cells[$characterId][$valueId]['alias'] ?? ''));
                $iconFile = $icons[$characterId][$valueId]['icon'] ?? null;

                if ($aliasText === '') {
                    if ($row) {
                        if ($row->icon) {
                            Storage::disk('public')->delete($row->icon);
                        }

                        $row->delete();
                    }

                    continue;
                }

                $attributes = ['alias' => $aliasText];

                if ($iconFile) {
                    if ($row?->icon) {
                        Storage::disk('public')->delete($row->icon);
                    }

                    $attributes['icon'] = $iconFile->store('resource-value-icons', 'public');
                }

                if ($row) {
                    $row->update($attributes);
                } else {
                    CharacterResourceValueAlias::create($attributes + [
                        'character_idcharacter' => $characterId,
                        'resources_values_idResources_values' => $valueId,
                    ]);
                }
            }
        }

        return redirect()->route('admin.resources.aliases', [$game, $resource])->with('status', 'Saved.');
    }
}
