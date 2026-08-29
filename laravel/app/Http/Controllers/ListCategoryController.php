<?php

namespace App\Http\Controllers;

use App\Models\ListCategory;
use App\Models\ListModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ListCategoryController extends Controller
{
    public function store(Request $request, ListModel $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $validated = $this->validateCategory($request, $list);

        ListCategory::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'list_idlist' => $list->idlist,
            'idPage' => $validated['idPage'] ?? null,
            'order' => $validated['order'] ?? null,
        ]);
        $list->recordEdit();

        return redirect()->route('lists.manage.index', $list)->with('status', 'Category added.');
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
