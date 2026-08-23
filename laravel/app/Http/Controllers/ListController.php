<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreListRequest;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListCategory;
use App\Models\ListModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ListController extends Controller
{
    public function index(): View
    {
        $games = Game::orderBy('name')->get();
        $lists = ListModel::with('user')->orderByDesc('idlist')->limit(50)->get();

        return view('lists.index', ['games' => $games, 'lists' => $lists]);
    }

    public function search(Request $request): View
    {
        $games = Game::orderBy('name')->get();

        $query = ListModel::with('user')
            ->where('type', '!=', 0)
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
            'user_iduser' => auth()->id(),
            'type' => 1,
        ]);

        return redirect()->route('lists.show', $list);
    }

    public function show(ListModel $list, Request $request): View|JsonResponse
    {
        $list->load('game', 'pages', 'user');

        if (! $request->wantsJson()) {
            $list->increment('views');
        }

        $pageId = $request->integer('page', 0);
        $currentPage = $list->pages->firstWhere('idListPage', $pageId);

        [$categories, $grouped] = $this->categoriesAndGroupedCombos($list, $pageId);

        if ($request->wantsJson()) {
            return response()->json([
                'pageId' => $pageId,
                'description' => view('lists._page-description', ['currentPage' => $currentPage])->render(),
                'content' => view('lists._page-body', ['categories' => $categories, 'grouped' => $grouped])->render(),
            ]);
        }

        return view('lists.show', [
            'list' => $list,
            'categories' => $categories,
            'grouped' => $grouped,
            'pageId' => $pageId,
            'currentPage' => $currentPage,
        ]);
    }

    public function manage(ListModel $list): View
    {
        $this->authorize('update', $list);

        $list->load('game', 'user');

        $pages = $list->pages()->orderBy('order')->orderBy('Title')->get();
        $categories = $list->categories()->with('page')->orderBy('order')->orderBy('title')->get();

        [, $grouped] = $this->categoriesAndGroupedCombos($list, null);

        return view('lists.manage.index', [
            'list' => $list,
            'pages' => $pages,
            'categories' => $categories,
            'grouped' => $grouped,
        ]);
    }

    /**
     * Categories for a list (optionally scoped to a page, mirroring the
     * page-nav filter on the public show() view) and its combos grouped by
     * category. Passing a null $pageId means "no page filter" — every
     * category and combo for the list, used by the management hub which
     * needs the full board for drag-and-drop.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function categoriesAndGroupedCombos(ListModel $list, ?int $pageId): array
    {
        $categoriesQuery = ListCategory::where('list_idlist', $list->idlist);

        if ($pageId !== null) {
            $categoriesQuery->where(function ($q) use ($pageId) {
                $q->where('idPage', $pageId)->orWhereNull('idPage');
            });
        }

        $categories = $categoriesQuery->orderBy('order')->orderBy('title')->get()->keyBy('idlist_category');

        $combos = $list->combos()
            ->with(['character', 'listingType', 'user'])
            ->orderByDesc('damage')
            ->get();

        if ($pageId !== null) {
            $combos = $combos->filter(function ($combo) use ($categories) {
                $categoryId = $combo->pivot->list_category_idlist_category;

                return $categoryId === null || $categories->has($categoryId);
            });
        }

        $grouped = $combos
            ->groupBy(fn ($combo) => $combo->pivot->list_category_idlist_category ?? 0)
            ->sortBy(fn ($group, $key) => $key == 0 ? -1 : ($categories->get($key)?->order ?? 0));

        return [$categories, $grouped];
    }

    public function rename(Request $request, ListModel $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'list_name' => ['required', 'string', 'max:100'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        $list->update(['list_name' => $validated['list_name']]);

        return redirect()->route('lists.show', $list)->with('status', 'List renamed.');
    }

    public function destroy(ListModel $list): RedirectResponse
    {
        $this->authorize('delete', $list);

        // TODO: record which user made this edit once an audit/edit-log exists
        DB::transaction(function () use ($list): void {
            $list->combos()->detach();
            $list->categories()->delete();
            $list->pages()->delete();
            $list->delete();
        });

        return redirect()->route('lists.index')->with('status', 'List deleted.');
    }

    /**
     * Remove combo entries from the list. Adding entries now goes through the
     * bulk combo picker (ListComboPickerController) instead of this free-text,
     * comma-separated flow — this endpoint keeps only the removal half, which
     * the management hub's per-combo "Remove" buttons reuse (single id or a
     * comma-separated batch).
     */
    public function alterEntries(Request $request, ListModel $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'comboid' => ['required', 'string'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        $comboIds = array_filter(array_map('trim', explode(',', $validated['comboid'])));

        $list->combos()->detach($comboIds);

        return redirect()->back()->with('status', 'Entry removed.');
    }

    /**
     * Reassign a combo already in the list to a different category (and, by
     * extension, page — a combo's page is inferred through its category's
     * idPage). This is the JSON endpoint the hub's drag-and-drop UI calls.
     */
    public function reassignEntry(Request $request, ListModel $list, Combo $combo): JsonResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'list_category_idlist_category' => ['nullable', 'integer', Rule::exists('list_category', 'idlist_category')->where('list_idlist', $list->idlist)],
        ]);

        if (! $list->combos()->where('combo_listing.idcombo', $combo->idcombo)->exists()) {
            return response()->json(['error' => 'That combo is not in this list.'], 422);
        }

        $combo->loadMissing('character');

        if ($list->game_idgame !== null && $combo->character->game_idgame !== $list->game_idgame) {
            return response()->json(['error' => 'That combo does not belong to this list\'s game.'], 422);
        }

        // TODO: record which user made this edit once an audit/edit-log exists
        $list->combos()->updateExistingPivot($combo->idcombo, [
            'list_category_idlist_category' => $validated['list_category_idlist_category'] ?? null,
        ]);

        return response()->json([
            'status' => 'ok',
            'combo_id' => $combo->idcombo,
            'category_id' => $validated['list_category_idlist_category'] ?? null,
        ]);
    }
}
