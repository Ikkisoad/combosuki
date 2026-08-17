<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Game;
use App\Services\GamePasswordChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CharacterController extends Controller
{
    public function __construct(private GamePasswordChecker $passwordChecker) {}

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
            'idcharacter' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
            'gamePass' => ['required', 'string', 'max:16'],
        ]);

        if (! $this->passwordChecker->check($game, $validated['gamePass'])) {
            return back()->with('error', 'Incorrect game password.');
        }

        if ($validated['action'] === 'Add') {
            Character::create(['name' => $validated['character'], 'game_idgame' => $game->idgame]);
        } elseif ($validated['action'] === 'Update') {
            Character::where('idcharacter', $validated['idcharacter'])
                ->where('game_idgame', $game->idgame)
                ->update(['name' => $validated['character']]);
        } else {
            $character = Character::where('idcharacter', $validated['idcharacter'])
                ->where('game_idgame', $game->idgame)
                ->first();

            $character?->combos()->each(function ($combo) {
                $combo->resources()->delete();
                $combo->delete();
            });

            $character?->delete();
        }

        return redirect()->route('admin.characters.index', $game)->with('status', 'Saved.');
    }
}
