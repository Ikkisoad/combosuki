<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\ListModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameListController extends Controller
{
    public function index(Game $game): View
    {
        $lists = ListModel::where('game_idgame', $game->idgame)->orderBy('list_name')->get();

        return view('admin.lists.index', ['game' => $game, 'lists' => $lists]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Update,Delete'],
            'idlist' => ['required', 'integer'],
            'listname' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'integer', 'in:0,1,2,3'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        $list = ListModel::where('idlist', $validated['idlist'])->where('game_idgame', $game->idgame)->first();

        if (! $list) {
            return redirect()->route('admin.lists.index', $game);
        }

        if ($validated['action'] === 'Delete') {
            $list->combos()->detach();
            $list->delete();

            return redirect()->route('admin.lists.index', $game)->with('status', 'List deleted.');
        }

        $update = ['type' => $validated['type'] ?? $list->type];

        if ($validated['listname'] ?? null) {
            $update['list_name'] = $validated['listname'];
        }

        $list->update($update);

        return redirect()->route('admin.lists.index', $game)->with('status', 'Saved.');
    }
}
