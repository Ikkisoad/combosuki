<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Button;
use App\Models\Character;
use App\Models\CharacterButtonAlias;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CharacterButtonAliasController extends Controller
{
    public function index(Game $game): View
    {
        $characterButtonAliases = CharacterButtonAlias::whereHas('character', fn ($q) => $q->where('game_idgame', $game->idgame))
            ->with(['button', 'character'])
            ->orderBy('alias')
            ->get();

        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();
        $buttons = Button::where('game_idgame', $game->idgame)->orderBy('order')->orderBy('name')->get();

        return view('admin.character-button-aliases.index', [
            'game' => $game,
            'characterButtonAliases' => $characterButtonAliases,
            'characters' => $characters,
            'buttons' => $buttons,
        ]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,Update,Delete'],
            'alias' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:45'],
            'button_idbutton' => [
                'required_if:action,Add,Update', 'nullable', 'integer',
                Rule::exists('button', 'idbutton')->where('game_idgame', $game->idgame),
            ],
            'character_idcharacter' => [
                'required_if:action,Add,Update', 'nullable', 'integer',
                Rule::exists('character', 'idcharacter')->where('game_idgame', $game->idgame),
            ],
            'idcharacterbuttonalias' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
        ]);

        if ($validated['action'] === 'Add') {
            if ($this->conflictingAlias((int) $validated['character_idcharacter'], $validated['alias'])) {
                return redirect()->route('admin.character-button-aliases.index', $game)
                    ->withErrors(["Alias \"{$validated['alias']}\" is already used by another alias for this character."])
                    ->withInput();
            }

            CharacterButtonAlias::create([
                'alias' => $validated['alias'],
                'button_idbutton' => $validated['button_idbutton'],
                'character_idcharacter' => $validated['character_idcharacter'],
            ]);
        } elseif ($validated['action'] === 'Update') {
            if ($this->conflictingAlias((int) $validated['character_idcharacter'], $validated['alias'], (int) $validated['idcharacterbuttonalias'])) {
                return redirect()->route('admin.character-button-aliases.index', $game)
                    ->withErrors(["Alias \"{$validated['alias']}\" is already used by another alias for this character."])
                    ->withInput();
            }

            CharacterButtonAlias::whereHas('character', fn ($q) => $q->where('game_idgame', $game->idgame))
                ->where('idcharacterbuttonalias', $validated['idcharacterbuttonalias'])
                ->update([
                    'alias' => $validated['alias'],
                    'button_idbutton' => $validated['button_idbutton'],
                    'character_idcharacter' => $validated['character_idcharacter'],
                ]);
        } else {
            CharacterButtonAlias::whereHas('character', fn ($q) => $q->where('game_idgame', $game->idgame))
                ->where('idcharacterbuttonalias', $validated['idcharacterbuttonalias'])
                ->delete();
        }

        return redirect()->route('admin.character-button-aliases.index', $game)->with('status', 'Saved.');
    }

    public function bulkUpdate(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'aliases' => ['required', 'array'],
            'aliases.*.alias' => ['required', 'string', 'max:45'],
            'aliases.*.button_idbutton' => [
                'required', 'integer',
                Rule::exists('button', 'idbutton')->where('game_idgame', $game->idgame),
            ],
            'aliases.*.character_idcharacter' => [
                'required', 'integer',
                Rule::exists('character', 'idcharacter')->where('game_idgame', $game->idgame),
            ],
        ]);

        $seenAliases = [];

        foreach ($validated['aliases'] as $idcharacterbuttonalias => $row) {
            $key = $row['character_idcharacter'].'-'.mb_strtolower($row['alias']);

            if (isset($seenAliases[$key]) && $seenAliases[$key] !== $idcharacterbuttonalias) {
                return redirect()->route('admin.character-button-aliases.index', $game)
                    ->withErrors(["Alias \"{$row['alias']}\" is used by more than one row for this character."])
                    ->withInput();
            }

            $seenAliases[$key] = $idcharacterbuttonalias;

            if ($this->conflictingAlias((int) $row['character_idcharacter'], $row['alias'], (int) $idcharacterbuttonalias)) {
                return redirect()->route('admin.character-button-aliases.index', $game)
                    ->withErrors(["Alias \"{$row['alias']}\" is already used by another alias for this character."])
                    ->withInput();
            }
        }

        DB::transaction(function () use ($validated, $game): void {
            foreach ($validated['aliases'] as $idcharacterbuttonalias => $row) {
                CharacterButtonAlias::whereHas('character', fn ($q) => $q->where('game_idgame', $game->idgame))
                    ->where('idcharacterbuttonalias', $idcharacterbuttonalias)
                    ->update([
                        'alias' => $row['alias'],
                        'button_idbutton' => $row['button_idbutton'],
                        'character_idcharacter' => $row['character_idcharacter'],
                    ]);
            }
        });

        return redirect()->route('admin.character-button-aliases.index', $game)->with('status', 'Saved.');
    }

    /**
     * Whether $alias is already used by another character_button_alias row
     * for $characterId (excluding $exceptId, e.g. the row being updated),
     * case-insensitively to match the DB's case-insensitive unique index.
     */
    private function conflictingAlias(int $characterId, string $alias, ?int $exceptId = null): bool
    {
        return CharacterButtonAlias::where('character_idcharacter', $characterId)
            ->when($exceptId, fn ($query) => $query->where('idcharacterbuttonalias', '!=', $exceptId))
            ->whereRaw('LOWER(alias) = ?', [mb_strtolower($alias)])
            ->exists();
    }
}
