<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameSettingsController extends Controller
{
    public function edit(Game $game): View
    {
        return view('admin.game.edit', ['game' => $game]);
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Submit,Delete,Lock,Unlock,Complete,Incomplete'],
            'title' => ['required_if:action,Submit', 'nullable', 'string', 'max:100'],
            'image' => ['nullable', 'string', 'max:255'],
            'patch' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:255'],
            'notation' => ['nullable', 'string', 'max:1000'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        if ($validated['action'] === 'Submit') {
            $game->update([
                'name' => $validated['title'],
                'image' => $validated['image'] ?? null,
                'patch' => $validated['patch'] ?? null,
                'description' => $validated['description'] ?? null,
                'notation' => $validated['notation'] ?? null,
            ]);

            return redirect()->route('admin.game.edit', $game)->with('status', 'Saved.');
        }

        if ($validated['action'] === 'Lock') {
            $game->update(['complete' => $game->isComplete() ? 2 : -1]);

            return redirect()->route('admin.game.edit', $game)->with('status', 'Game locked.');
        }

        if ($validated['action'] === 'Unlock') {
            $game->update(['complete' => $game->isComplete() ? 1 : 0]);

            return redirect()->route('admin.game.edit', $game)->with('status', 'Game unlocked.');
        }

        if ($validated['action'] === 'Complete' || $validated['action'] === 'Incomplete') {
            abort_unless($request->user()->is_admin, 403);

            $complete = $validated['action'] === 'Complete';
            $game->update(['complete' => match (true) {
                $complete && $game->isLocked() => 2,
                $complete => 1,
                $game->isLocked() => -1,
                default => 0,
            }]);

            return redirect()->route('admin.game.edit', $game)
                ->with('status', $complete ? 'Game marked as complete.' : 'Game marked as incomplete.');
        }

        // FK cascades handle characters/combos/resources/buttons, but
        // list.game_idgame is nullOnDelete (lists can be cross-game), so the
        // game's own lists need explicit deletion, matching legacy.
        $game->lists()->each(function ($list) {
            $list->combos()->detach();
            $list->categories()->delete();
            $list->delete();
        });

        $game->delete();

        return redirect()->route('games.index')->with('status', 'Game deleted.');
    }
}
