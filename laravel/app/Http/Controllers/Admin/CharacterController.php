<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\CharacterAlias;
use App\Models\Game;
use App\Support\AliasGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CharacterController extends Controller
{
    public function index(Game $game): View
    {
        $characters = Character::where('game_idgame', $game->idgame)->with('aliases', 'links')->orderBy('name')->get();

        return view('admin.characters.index', ['game' => $game, 'characters' => $characters]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,Update,Delete'],
            'character' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:45'],
            'image' => ['nullable', 'image', 'max:5120'],
            'idcharacter' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
            'aliases' => ['nullable', 'string', 'max:1000'],
            'links' => ['nullable', 'string', 'max:2000'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        if ($validated['action'] === 'Add') {
            $aliases = AliasGenerator::parseList($validated['aliases'] ?? '');

            $conflict = $this->conflictingAlias($game, $aliases);

            if ($conflict) {
                return redirect()->route('admin.characters.index', $game)
                    ->withErrors(["Alias \"{$conflict}\" is already used by another character in this game."])
                    ->withInput();
            }

            $character = Character::create([
                'name' => $validated['character'],
                'image' => $request->hasFile('image') ? $request->file('image')->store('character-portraits', 'public') : null,
                'game_idgame' => $game->idgame,
            ]);

            $this->syncAliases($character, $game, $aliases);
            $this->syncLinks($character, $validated['links'] ?? '');
        } elseif ($validated['action'] === 'Update') {
            $character = Character::where('idcharacter', $validated['idcharacter'])
                ->where('game_idgame', $game->idgame)
                ->first();

            $aliases = AliasGenerator::parseList($validated['aliases'] ?? '');

            $conflict = $character ? $this->conflictingAlias($game, $aliases, $character->idcharacter) : null;

            if ($conflict) {
                return redirect()->route('admin.characters.index', $game)
                    ->withErrors(["Alias \"{$conflict}\" is already used by another character in this game."])
                    ->withInput();
            }

            $attributes = ['name' => $validated['character']];

            if ($character && $request->hasFile('image')) {
                if ($character->image) {
                    Storage::disk('public')->delete($character->image);
                }

                $attributes['image'] = $request->file('image')->store('character-portraits', 'public');
            }

            $character?->update($attributes);

            if ($character) {
                $this->syncAliases($character, $game, $aliases);
                $this->syncLinks($character, $validated['links'] ?? '');
            }
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
            'characters.*.aliases' => ['nullable', 'string', 'max:1000'],
            'characters.*.links' => ['nullable', 'string', 'max:2000'],
        ]);

        $aliasesByCharacter = [];
        $seenAliases = [];

        foreach ($validated['characters'] as $idcharacter => $row) {
            $aliases = AliasGenerator::parseList($row['aliases'] ?? '');
            $aliasesByCharacter[$idcharacter] = $aliases;

            foreach ($aliases as $alias) {
                $key = mb_strtolower($alias);

                if (isset($seenAliases[$key]) && $seenAliases[$key] !== $idcharacter) {
                    return redirect()->route('admin.characters.index', $game)
                        ->withErrors(["Alias \"{$alias}\" is used by more than one character in this game."])
                        ->withInput();
                }

                $seenAliases[$key] = $idcharacter;
            }

            $conflict = $this->conflictingAlias($game, $aliases, (int) $idcharacter);

            if ($conflict) {
                return redirect()->route('admin.characters.index', $game)
                    ->withErrors(["Alias \"{$conflict}\" is already used by another character in this game."])
                    ->withInput();
            }
        }

        // TODO: record which user made this edit once an audit/edit-log exists
        DB::transaction(function () use ($validated, $game, $aliasesByCharacter): void {
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

                $this->syncAliases($character, $game, $aliasesByCharacter[$idcharacter]);
                $this->syncLinks($character, $row['links'] ?? '');
            }
        });

        return redirect()->route('admin.characters.index', $game)->with('status', 'Saved.');
    }

    /**
     * Return the first of $aliases already used by another character in
     * $game (excluding $exceptCharacterId, e.g. the character being
     * updated), or null if none conflict.
     */
    private function conflictingAlias(Game $game, array $aliases, ?int $exceptCharacterId = null): ?string
    {
        if ($aliases === []) {
            return null;
        }

        return CharacterAlias::where('game_idgame', $game->idgame)
            ->when($exceptCharacterId, fn ($query) => $query->where('character_idcharacter', '!=', $exceptCharacterId))
            ->whereIn('alias', $aliases)
            ->value('alias');
    }

    /**
     * Replace $character's alias rows with $aliases, matching case-insensitively
     * (mirroring the DB's case-insensitive unique index) so re-submitting an
     * existing alias with different casing doesn't delete-then-reinsert it
     * (or worse, collide with itself on the unique index).
     */
    private function syncAliases(Character $character, Game $game, array $aliases): void
    {
        $wanted = collect($aliases)->keyBy(fn ($alias) => mb_strtolower($alias));
        $existing = $character->aliases()->get();

        foreach ($existing as $row) {
            if (! $wanted->has(mb_strtolower($row->alias))) {
                $row->delete();
            }
        }

        $existingKeys = $existing->map(fn ($row) => mb_strtolower($row->alias))->all();

        foreach ($wanted as $key => $alias) {
            if (! in_array($key, $existingKeys, true)) {
                $character->aliases()->create(['alias' => $alias, 'game_idgame' => $game->idgame]);
            }
        }
    }

    /**
     * Replace $character's links with the ones parsed from $raw (one
     * "Label|https://url" per line). Links have no identity worth
     * preserving across edits, so it's simplest to just delete and
     * recreate them in the submitted order.
     */
    private function syncLinks(Character $character, string $raw): void
    {
        $character->links()->delete();

        $order = 0;

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, '|')) {
                continue;
            }

            [$label, $url] = array_map('trim', explode('|', $line, 2));

            if ($label === '' || $url === '') {
                continue;
            }

            $character->links()->create([
                'label' => mb_substr($label, 0, 100),
                'url' => mb_substr($url, 0, 255),
                'order' => $order++,
            ]);
        }
    }
}
