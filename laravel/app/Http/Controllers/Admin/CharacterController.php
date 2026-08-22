<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CharacterController extends Controller
{
    public function index(Game $game): View
    {
        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        return view('admin.characters.index', ['game' => $game, 'characters' => $characters]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,Update,Delete'],
            'character' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:45'],
            'image' => ['nullable', 'image', 'max:5120'],
            'idcharacter' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        if ($validated['action'] === 'Add') {
            Character::create([
                'name' => $validated['character'],
                'image' => $request->hasFile('image') ? $request->file('image')->store('character-portraits', 'public') : null,
                'game_idgame' => $game->idgame,
            ]);
        } elseif ($validated['action'] === 'Update') {
            $character = Character::where('idcharacter', $validated['idcharacter'])
                ->where('game_idgame', $game->idgame)
                ->first();

            $attributes = ['name' => $validated['character']];

            if ($character && $request->hasFile('image')) {
                if ($character->image) {
                    Storage::disk('public')->delete($character->image);
                }

                $attributes['image'] = $request->file('image')->store('character-portraits', 'public');
            }

            $character?->update($attributes);
        } else {
            $character = Character::where('idcharacter', $validated['idcharacter'])
                ->where('game_idgame', $game->idgame)
                ->first();

            $character?->combos()->each(function ($combo) {
                $combo->resources()->delete();
                $combo->delete();
            });

            if ($character?->image) {
                Storage::disk('public')->delete($character->image);
            }

            $character?->delete();
        }

        return redirect()->route('admin.characters.index', $game)->with('status', 'Saved.');
    }

    public function bulkUpdate(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'characters' => ['required', 'array'],
            'characters.*.name' => ['required', 'string', 'max:45'],
            'characters.*.image' => ['nullable', 'image', 'max:5120'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        DB::transaction(function () use ($validated, $game): void {
            foreach ($validated['characters'] as $idcharacter => $row) {
                $character = Character::where('idcharacter', $idcharacter)
                    ->where('game_idgame', $game->idgame)
                    ->first();

                if (! $character) {
                    continue;
                }

                $attributes = ['name' => $row['name']];

                if (isset($row['image'])) {
                    if ($character->image) {
                        Storage::disk('public')->delete($character->image);
                    }

                    $attributes['image'] = $row['image']->store('character-portraits', 'public');
                }

                $character->update($attributes);
            }
        });

        return redirect()->route('admin.characters.index', $game)->with('status', 'Saved.');
    }
}
