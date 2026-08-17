<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreComboRequest;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameResource;
use App\Models\Resource;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ComboController extends Controller
{
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
            'password' => $validated['password'],
        ]);

        foreach ($validated['resources'] ?? [] as $idGameResources => $value) {
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

        return redirect()->route('combos.show', $combo)->with('status', 'Combo submitted.');
    }
}
