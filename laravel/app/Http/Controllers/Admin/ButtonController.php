<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Button;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ButtonController extends Controller
{
    public function index(Game $game): View
    {
        $buttons = Button::where('game_idgame', $game->idgame)->orderBy('order')->orderBy('name')->get();

        return view('admin.buttons.index', ['game' => $game, 'buttons' => $buttons]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,Update,Delete'],
            'name' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:45'],
            'color' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:7'],
            'match_type' => ['required_if:action,Add,Update', 'nullable', 'string', 'in:contains,starts_with,ends_with,exact'],
            'order' => ['nullable', 'numeric'],
            'idbutton' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        $name = isset($validated['name']) ? str_replace(' ', '', $validated['name']) : null;

        if ($validated['action'] === 'Add') {
            Button::create([
                'name' => $name,
                'color' => $validated['color'],
                'match_type' => $validated['match_type'],
                'game_idgame' => $game->idgame,
                'order' => $validated['order'] ?? null,
            ]);
        } elseif ($validated['action'] === 'Update') {
            Button::where('idbutton', $validated['idbutton'])
                ->where('game_idgame', $game->idgame)
                ->update([
                    'name' => $name,
                    'color' => $validated['color'],
                    'match_type' => $validated['match_type'],
                    'order' => $validated['order'] ?? null,
                ]);
        } else {
            Button::where('idbutton', $validated['idbutton'])->where('game_idgame', $game->idgame)->delete();
        }

        return redirect()->route('admin.buttons.index', $game)->with('status', 'Saved.');
    }
}
