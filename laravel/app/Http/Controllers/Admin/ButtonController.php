<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Button;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function bulkUpdate(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'buttons' => ['required', 'array'],
            'buttons.*.name' => ['required', 'string', 'max:45'],
            'buttons.*.color' => ['required', 'string', 'max:7'],
            'buttons.*.match_type' => ['required', 'string', 'in:contains,starts_with,ends_with,exact'],
            'buttons.*.order' => ['nullable', 'numeric'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        DB::transaction(function () use ($validated, $game): void {
            foreach ($validated['buttons'] as $idbutton => $row) {
                Button::where('idbutton', $idbutton)
                    ->where('game_idgame', $game->idgame)
                    ->update([
                        'name' => str_replace(' ', '', $row['name']),
                        'color' => $row['color'],
                        'match_type' => $row['match_type'],
                        'order' => $row['order'] ?? null,
                    ]);
            }
        });

        return redirect()->route('admin.buttons.index', $game)->with('status', 'Saved.');
    }
}
