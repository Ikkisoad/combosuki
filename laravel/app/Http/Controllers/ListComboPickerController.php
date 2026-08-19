<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Models\Character;
use App\Models\Combo;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\ListModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ListComboPickerController extends Controller
{
    use FiltersCombos;

    public function index(ListModel $list, Request $request): View
    {
        $this->authorize('update', $list);

        if ($list->game_idgame === null) {
            return view('lists.manage.combos', [
                'list' => $list,
                'needsGame' => true,
            ]);
        }

        $game = $list->game;

        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        $listingTypes = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();

        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->with('values')
            ->orderBy('text_name')
            ->get();

        $query = Combo::query()
            ->with(['character', 'listingType', 'resources.resourceValue', 'user'])
            ->whereHas('character', fn (Builder $q) => $q->where('game_idgame', $game->idgame))
            ->whereDoesntHave('lists', fn (Builder $q) => $q->where('list.idlist', $list->idlist));

        $this->applyFilters($query, $request, $primaryResources);
        $this->applyOrdering($query, $request);

        $combos = $query->paginate(20)->withQueryString();

        $categories = $list->categories()->with('page')->orderBy('order')->orderBy('title')->get();

        return view('lists.manage.combos', [
            'list' => $list,
            'game' => $game,
            'needsGame' => false,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
            'primaryResources' => $primaryResources,
            'combos' => $combos,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request, ListModel $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'combo_ids' => ['required', 'array'],
            'combo_ids.*' => ['integer', 'exists:combo,idcombo'],
            'category_id' => ['nullable', 'integer', Rule::exists('list_category', 'idlist_category')->where('list_idlist', $list->idlist)],
        ]);

        $categoryId = $validated['category_id'] ?? null;
        $added = 0;

        // TODO: record which user made this edit once an audit/edit-log exists
        foreach ($validated['combo_ids'] as $comboId) {
            $combo = Combo::with('character')->find($comboId);

            if (! $combo) {
                continue;
            }

            if ($list->game_idgame !== null && $combo->character->game_idgame !== $list->game_idgame) {
                continue;
            }

            $list->combos()->syncWithoutDetaching([
                $combo->idcombo => ['list_category_idlist_category' => $categoryId],
            ]);

            $added++;
        }

        return redirect()->route('lists.manage.combos.index', $list)->with('status', "{$added} combo(s) added.");
    }
}
