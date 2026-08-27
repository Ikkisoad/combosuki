<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Button;
use App\Models\ButtonAlias;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ButtonAliasController extends Controller
{
    public function index(Game $game): View
    {
        $buttonAliases = ButtonAlias::where('game_idgame', $game->idgame)->with('button')->orderBy('alias')->get();
        $buttons = Button::where('game_idgame', $game->idgame)->orderBy('order')->orderBy('name')->get();

        return view('admin.button-aliases.index', ['game' => $game, 'buttonAliases' => $buttonAliases, 'buttons' => $buttons]);
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
            'idbuttonalias' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
        ]);

        if ($validated['action'] === 'Add') {
            if ($this->conflictingAlias($game, $validated['alias'])) {
                return redirect()->route('admin.button-aliases.index', $game)
                    ->withErrors(["Alias \"{$validated['alias']}\" is already used by another alias in this game."])
                    ->withInput();
            }

            ButtonAlias::create([
                'alias' => $validated['alias'],
                'button_idbutton' => $validated['button_idbutton'],
                'game_idgame' => $game->idgame,
            ]);
        } elseif ($validated['action'] === 'Update') {
            if ($this->conflictingAlias($game, $validated['alias'], (int) $validated['idbuttonalias'])) {
                return redirect()->route('admin.button-aliases.index', $game)
                    ->withErrors(["Alias \"{$validated['alias']}\" is already used by another alias in this game."])
                    ->withInput();
            }

            ButtonAlias::where('idbuttonalias', $validated['idbuttonalias'])
                ->where('game_idgame', $game->idgame)
                ->update([
                    'alias' => $validated['alias'],
                    'button_idbutton' => $validated['button_idbutton'],
                ]);
        } else {
            ButtonAlias::where('idbuttonalias', $validated['idbuttonalias'])->where('game_idgame', $game->idgame)->delete();
        }

        return redirect()->route('admin.button-aliases.index', $game)->with('status', 'Saved.');
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
        ]);

        $seenAliases = [];

        foreach ($validated['aliases'] as $idbuttonalias => $row) {
            $key = mb_strtolower($row['alias']);

            if (isset($seenAliases[$key]) && $seenAliases[$key] !== $idbuttonalias) {
                return redirect()->route('admin.button-aliases.index', $game)
                    ->withErrors(["Alias \"{$row['alias']}\" is used by more than one row."])
                    ->withInput();
            }

            $seenAliases[$key] = $idbuttonalias;

            if ($this->conflictingAlias($game, $row['alias'], (int) $idbuttonalias)) {
                return redirect()->route('admin.button-aliases.index', $game)
                    ->withErrors(["Alias \"{$row['alias']}\" is already used by another alias in this game."])
                    ->withInput();
            }
        }

        DB::transaction(function () use ($validated, $game): void {
            foreach ($validated['aliases'] as $idbuttonalias => $row) {
                ButtonAlias::where('idbuttonalias', $idbuttonalias)
                    ->where('game_idgame', $game->idgame)
                    ->update([
                        'alias' => $row['alias'],
                        'button_idbutton' => $row['button_idbutton'],
                    ]);
            }
        });

        return redirect()->route('admin.button-aliases.index', $game)->with('status', 'Saved.');
    }

    /**
     * Whether $alias is already used by another button_alias row in $game
     * (excluding $exceptId, e.g. the row being updated), case-insensitively
     * to match the DB's case-insensitive unique index.
     */
    private function conflictingAlias(Game $game, string $alias, ?int $exceptId = null): bool
    {
        return ButtonAlias::where('game_idgame', $game->idgame)
            ->when($exceptId, fn ($query) => $query->where('idbuttonalias', '!=', $exceptId))
            ->whereRaw('LOWER(alias) = ?', [mb_strtolower($alias)])
            ->exists();
    }
}
