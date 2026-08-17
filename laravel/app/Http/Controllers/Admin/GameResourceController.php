<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameResource;
use App\Models\ResourceValue;
use App\Services\GamePasswordChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameResourceController extends Controller
{
    public function __construct(private GamePasswordChecker $passwordChecker) {}

    public function index(Game $game): View
    {
        $resources = GameResource::where('game_idgame', $game->idgame)
            ->orderByDesc('primaryORsecundary')
            ->orderBy('text_name')
            ->get();

        return view('admin.resources.index', ['game' => $game, 'resources' => $resources]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,Update,Delete'],
            'resource' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:45'],
            'type' => ['required_if:action,Add,Update', 'nullable', 'integer', 'in:1,2,3'],
            'primaryORsecundary' => ['nullable', 'integer', 'in:0,1'],
            'primaryorsecundary' => ['nullable', 'integer', 'in:0,1'],
            'idresource' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
            'gamePass' => ['required', 'string', 'max:16'],
        ]);

        if (! $this->passwordChecker->check($game, $validated['gamePass'])) {
            return back()->with('error', 'Incorrect game password.');
        }

        $primary = $validated['primaryORsecundary'] ?? $validated['primaryorsecundary'] ?? 0;

        if ($validated['action'] === 'Add') {
            GameResource::create([
                'game_idgame' => $game->idgame,
                'text_name' => $validated['resource'],
                'type' => $validated['type'],
                'primaryORsecundary' => $primary,
            ]);
        } elseif ($validated['action'] === 'Update') {
            GameResource::where('idgame_resources', $validated['idresource'])
                ->where('game_idgame', $game->idgame)
                ->update([
                    'text_name' => $validated['resource'],
                    'type' => $validated['type'],
                    'primaryORsecundary' => $primary,
                ]);
        } else {
            $resource = GameResource::where('idgame_resources', $validated['idresource'])
                ->where('game_idgame', $game->idgame)
                ->first();

            $resource?->values->each(fn ($value) => $value->delete());
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

        $validated = $request->validate([
            'action' => ['required', 'in:EditAdd,EditUpdate,EditDelete'],
            'resourcevalue' => ['required_if:action,EditAdd,EditUpdate', 'nullable', 'string', 'max:115'],
            'order' => ['nullable', 'numeric'],
            'idresourcevalue' => ['required_if:action,EditUpdate,EditDelete', 'nullable', 'integer'],
            'gamePass' => ['required', 'string', 'max:16'],
        ]);

        if (! $this->passwordChecker->check($game, $validated['gamePass'])) {
            return back()->with('error', 'Incorrect game password.');
        }

        if ($validated['action'] === 'EditAdd') {
            ResourceValue::create([
                'value' => $validated['resourcevalue'],
                'order' => $validated['order'] ?? null,
                'game_resources_idgame_resources' => $resource->idgame_resources,
            ]);
        } elseif ($validated['action'] === 'EditUpdate') {
            ResourceValue::where('idResources_values', $validated['idresourcevalue'])
                ->where('game_resources_idgame_resources', $resource->idgame_resources)
                ->update(['value' => $validated['resourcevalue'], 'order' => $validated['order'] ?? null]);
        } else {
            ResourceValue::where('idResources_values', $validated['idresourcevalue'])
                ->where('game_resources_idgame_resources', $resource->idgame_resources)
                ->delete();
        }

        return redirect()->route('admin.resources.values', [$game, $resource])->with('status', 'Saved.');
    }
}
