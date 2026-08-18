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

        $images = collect(glob(public_path('img/buttons/*.png')))
            ->map(fn ($path) => basename($path, '.png'))
            ->sort()
            ->values();

        return view('admin.buttons.index', ['game' => $game, 'buttons' => $buttons, 'images' => $images]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,Update,Delete'],
            'name' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:45'],
            'png' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:45'],
            'order' => ['nullable', 'numeric'],
            'idbutton' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        $name = isset($validated['name']) ? str_replace(' ', '', $validated['name']) : null;

        if ($validated['action'] === 'Add') {
            Button::create(['name' => $name, 'png' => $validated['png'], 'game_idgame' => $game->idgame, 'order' => $validated['order'] ?? null]);
        } elseif ($validated['action'] === 'Update') {
            Button::where('idbutton', $validated['idbutton'])
                ->where('game_idgame', $game->idgame)
                ->update(['name' => $name, 'png' => $validated['png'], 'order' => $validated['order'] ?? null]);
        } else {
            Button::where('idbutton', $validated['idbutton'])->where('game_idgame', $game->idgame)->delete();
        }

        return redirect()->route('admin.buttons.index', $game)->with('status', 'Saved.');
    }
}
