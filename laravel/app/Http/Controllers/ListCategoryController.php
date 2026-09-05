<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Models\GameResource;
use App\Models\ListCategory;
use App\Models\ListModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ListCategoryController extends Controller
{
    use FiltersCombos;

    /**
     * Ceiling on how many combos a creator can configure a category's query
     * to pull in — see ListController::categoriesAndGroupedCombos(), which
     * feeds a category with at most this many query matches.
     */
    private const MAX_QUERY_LIMIT = 3;

    public function store(Request $request, ListModel $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $validated = $this->validateCategory($request, $list);

        $query = $list->game_idgame !== null
            ? $this->buildCategoryQuery($request, $list)
            : ['filters' => null, 'limit' => null];

        ListCategory::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'list_idlist' => $list->idlist,
            'idPage' => $validated['idPage'] ?? null,
            'order' => $validated['order'] ?? null,
            'filters' => $query['filters'],
            'query_limit' => $query['limit'],
        ]);
        $list->recordEdit();

        return redirect()->route('lists.manage.index', $list)->with('status', 'Category added.');
    }

    /**
     * Save (or clear, if every field is left blank) a category's query —
     * the same filter-set mechanism a game's default queries use (see
     * FiltersCombos), reused here so a category can feed itself from
     * matching combos instead of relying only on manually-tagged ones.
     */
    public function updateFilters(Request $request, ListModel $list, ListCategory $category): RedirectResponse
    {
        $this->authorize('update', $list);

        abort_if($category->list_idlist !== $list->idlist, 404);
        abort_if($list->game_idgame === null, 422, 'This guide has no game, so its categories cannot have a query.');

        $query = $this->buildCategoryQuery($request, $list);

        $category->update(['filters' => $query['filters'], 'query_limit' => $query['limit']]);
        $list->recordEdit();

        return redirect()->route('lists.manage.index', $list)->with('status', 'Category query saved.');
    }

    /**
     * @return array{filters: ?array, limit: ?int}
     */
    private function buildCategoryQuery(Request $request, ListModel $list): array
    {
        if ($request->input('characterid') === '-') {
            $request->merge(['characterid' => null]);
        }

        $validated = $request->validate([
            'characterid' => ['nullable', Rule::exists('character', 'idcharacter')->where('game_idgame', $list->game_idgame)],
            'query_limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_QUERY_LIMIT],
        ]);

        $primaryResources = GameResource::where('game_idgame', $list->game_idgame)
            ->where('primaryORsecundary', 1)
            ->get();

        $filters = $this->buildFiltersFromRequest($request, $primaryResources);

        if ($filters === []) {
            return ['filters' => null, 'limit' => null];
        }

        return ['filters' => $filters, 'limit' => $validated['query_limit'] ?? 1];
    }

    public function destroy(ListModel $list, ListCategory $category): RedirectResponse
    {
        $this->authorize('update', $list);

        abort_if($category->list_idlist !== $list->idlist, 404);

        $category->delete();
        $list->recordEdit('deleted');

        return redirect()->route('lists.manage.index', $list)->with('status', 'Category deleted. Its combos are now uncategorized.');
    }

    /**
     * Save every category row's edits (title/page/order) in one request.
     * Called via fetch() from the hub's single "Save All Categories" button.
     */
    public function bulkUpdate(Request $request, ListModel $list): JsonResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'categories' => ['required', 'array'],
            'categories.*.title' => ['required', 'string', 'max:50'],
            'categories.*.description' => ['nullable', 'string', 'max:1000'],
            'categories.*.idPage' => ['nullable', 'integer', Rule::exists('list_page', 'idListPage')->where('idList', $list->idlist)],
            'categories.*.order' => ['nullable', 'numeric'],
        ]);

        DB::transaction(function () use ($validated, $list): void {
            foreach ($validated['categories'] as $idCategory => $row) {
                ListCategory::where('idlist_category', $idCategory)
                    ->where('list_idlist', $list->idlist)
                    ->update([
                        'title' => $row['title'],
                        'description' => $row['description'] ?? null,
                        'idPage' => $row['idPage'] ?? null,
                        'order' => $row['order'] ?? null,
                    ]);
            }
        });
        $list->recordEdit();

        return response()->json(['status' => 'ok']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, ListModel $list): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'idPage' => ['nullable', 'integer', Rule::exists('list_page', 'idListPage')->where('idList', $list->idlist)],
            'order' => ['nullable', 'numeric'],
        ]);
    }
}
