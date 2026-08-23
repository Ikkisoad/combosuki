<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameAlias;
use App\Support\AliasGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            'aliases' => ['nullable', 'string', 'max:1000'],
            'matches_url' => ['nullable', 'string', 'max:255'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        if ($validated['action'] === 'Submit') {
            $aliases = AliasGenerator::parseList($validated['aliases'] ?? '');

            if ($aliases !== []) {
                $conflict = GameAlias::where('game_idgame', '!=', $game->idgame)
                    ->whereIn('alias', $aliases)
                    ->first();

                if ($conflict) {
                    return back()->withErrors(["Alias \"{$conflict->alias}\" is already used by another game."])->withInput();
                }
            }

            $game->update([
                'name' => $validated['title'],
                'image' => $validated['image'] ?? null,
                'patch' => $validated['patch'] ?? null,
                'description' => $validated['description'] ?? null,
                'notation' => $validated['notation'] ?? null,
                'matches_enabled' => $request->boolean('matches_enabled'),
                'matches_url' => $validated['matches_url'] ?? null,
            ]);

            // Matched case-insensitively (mirroring the DB's case-insensitive
            // unique index) so re-submitting an existing alias with different
            // casing doesn't delete-then-reinsert it.
            $wanted = collect($aliases)->keyBy(fn ($alias) => Str::lower($alias));
            $existingAliases = $game->aliases()->get();

            foreach ($existingAliases as $row) {
                if (! $wanted->has(Str::lower($row->alias))) {
                    $row->delete();
                }
            }

            $existingKeys = $existingAliases->map(fn ($row) => Str::lower($row->alias))->all();

            foreach ($wanted as $key => $alias) {
                if (! in_array($key, $existingKeys, true)) {
                    $game->aliases()->create(['alias' => $alias]);
                }
            }

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
