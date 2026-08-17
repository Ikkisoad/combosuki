<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreListRequest;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListCategory;
use App\Models\ListModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListController extends Controller
{
    public function index(): View
    {
        $games = Game::orderBy('name')->get();
        $lists = ListModel::orderByDesc('idlist')->limit(50)->get();

        return view('lists.index', ['games' => $games, 'lists' => $lists]);
    }

    public function search(Request $request): View
    {
        $games = Game::orderBy('name')->get();

        $query = ListModel::where('type', '!=', 0)
            ->orderByDesc('type')
            ->orderBy('list_name')
            ->limit(50);

        if ($request->filled('list_name')) {
            $query->where('list_name', 'like', '%'.$request->string('list_name').'%');
        }

        if ($request->filled('game_idgame')) {
            $query->where('game_idgame', $request->integer('game_idgame'));
        }

        $lists = $request->hasAny(['list_name', 'game_idgame']) ? $query->get() : collect();

        return view('lists.index', [
            'games' => $games,
            'lists' => $lists,
            'searched' => $request->hasAny(['list_name', 'game_idgame']),
        ]);
    }

    public function store(StoreListRequest $request): RedirectResponse
    {
        $list = ListModel::create([
            'list_name' => $request->string('list_name'),
            'game_idgame' => $request->integer('game_idgame') ?: null,
            'password' => $request->string('password'),
            'type' => 1,
        ]);

        return redirect()->route('lists.show', $list);
    }

    public function show(ListModel $list, Request $request): View
    {
        $list->load('game', 'pages');

        $pageId = $request->integer('page', 0);

        $categories = ListCategory::where('list_idlist', $list->idlist)
            ->where(function ($q) use ($pageId) {
                $q->where('idPage', $pageId)->orWhereNull('idPage');
            })
            ->orderBy('order')
            ->orderBy('title')
            ->get()
            ->keyBy('idlist_category');

        $combos = $list->combos()
            ->with(['character', 'listingType'])
            ->orderByDesc('damage')
            ->get()
            ->filter(function ($combo) use ($categories) {
                $categoryId = $combo->pivot->list_category_idlist_category;

                return $categoryId === null || $categories->has($categoryId);
            });

        $grouped = $combos
            ->groupBy(fn ($combo) => $combo->pivot->list_category_idlist_category ?? 0)
            ->sortBy(fn ($group, $key) => $key == 0 ? -1 : ($categories->get($key)?->order ?? 0));

        return view('lists.show', [
            'list' => $list,
            'categories' => $categories,
            'grouped' => $grouped,
            'pageId' => $pageId,
        ]);
    }

    public function rename(Request $request, ListModel $list): RedirectResponse
    {
        $validated = $request->validate([
            'list_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:16'],
        ]);

        if (! $this->passwordMatches($list, $validated['password'])) {
            return redirect()->route('lists.show', $list)->with('error', 'Incorrect list password.');
        }

        $list->update(['list_name' => $validated['list_name']]);

        return redirect()->route('lists.show', $list)->with('status', 'List renamed.');
    }

    public function destroy(Request $request, ListModel $list): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:16'],
        ]);

        $modPass = $list->game?->modPass;

        if ($modPass && password_verify($validated['password'], $modPass)) {
            $list->update(['type' => 0]);
        }

        if ($this->passwordMatches($list, $validated['password'])) {
            $list->combos()->detach();
            $list->categories()->delete();
            $list->delete();

            return redirect()->route('lists.index')->with('status', 'List deleted.');
        }

        return redirect()->route('lists.show', $list)->with('error', 'Incorrect list password.');
    }

    /**
     * Add or remove combo entries from the list. Mirrors legacy's alter_List,
     * which uses one form and an `action` field (Submit = add, Delete = remove)
     * rather than two separate endpoints.
     */
    public function alterEntries(Request $request, ListModel $list): RedirectResponse
    {
        $validated = $request->validate([
            'comboid' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:45'],
            'password' => ['required', 'string', 'max:16'],
            'action' => ['required', 'in:Submit,Delete'],
        ]);

        if (! $this->passwordMatches($list, $validated['password'])) {
            return redirect()->route('lists.show', $list)->with('error', 'Incorrect list password.');
        }

        $comboIds = array_filter(array_map('trim', explode(',', $validated['comboid'])));

        if ($validated['action'] === 'Delete') {
            $list->combos()->detach($comboIds);

            return redirect()->route('lists.show', $list)->with('status', 'Entry removed.');
        }

        $categoryId = null;

        if ($validated['category'] ?? null) {
            $categoryId = ListCategory::create([
                'title' => $validated['category'],
                'list_idlist' => $list->idlist,
                'order' => 0,
            ])->idlist_category;
        }

        foreach ($comboIds as $comboId) {
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
        }

        return redirect()->route('lists.show', $list)->with('status', 'Entry added.');
    }

    private function passwordMatches(ListModel $list, string $submitted): bool
    {
        if (hash_equals($list->password, $submitted)) {
            return true;
        }

        $game = $list->game;

        if (! $game) {
            return false;
        }

        if ($game->globalPass !== null && hash_equals($game->globalPass, $submitted)) {
            return true;
        }

        return password_verify($submitted, $game->modPass);
    }
}
