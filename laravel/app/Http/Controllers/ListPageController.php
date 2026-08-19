<?php

namespace App\Http\Controllers;

use App\Models\ListModel;
use App\Models\ListPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListPageController extends Controller
{
    public function store(Request $request, ListModel $list): RedirectResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'Title' => ['required', 'string', 'max:255'],
            'Description' => ['nullable', 'string', 'max:1000'],
            'order' => ['nullable', 'numeric'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        ListPage::create([
            'Title' => $validated['Title'],
            'Description' => $validated['Description'] ?? null,
            'idList' => $list->idlist,
            'order' => $validated['order'] ?? null,
        ]);

        return redirect()->route('lists.manage.index', $list)->with('status', 'Page added.');
    }

    public function destroy(ListModel $list, ListPage $page): RedirectResponse
    {
        $this->authorize('update', $list);

        abort_if($page->idList !== $list->idlist, 404);

        // TODO: record which user made this edit once an audit/edit-log exists
        $page->delete();

        return redirect()->route('lists.manage.index', $list)->with('status', 'Page deleted. Its categories are now unassigned from any page.');
    }

    /**
     * Save every page row's edits (Title/Description/order) in one request.
     * Called via fetch() from the hub's single "Save All Pages" button.
     */
    public function bulkUpdate(Request $request, ListModel $list): JsonResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'pages' => ['required', 'array'],
            'pages.*.Title' => ['required', 'string', 'max:255'],
            'pages.*.Description' => ['nullable', 'string', 'max:1000'],
            'pages.*.order' => ['nullable', 'numeric'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        DB::transaction(function () use ($validated, $list): void {
            foreach ($validated['pages'] as $idListPage => $row) {
                ListPage::where('idListPage', $idListPage)
                    ->where('idList', $list->idlist)
                    ->update([
                        'Title' => $row['Title'],
                        'Description' => $row['Description'] ?? null,
                        'order' => $row['order'] ?? null,
                    ]);
            }
        });

        return response()->json(['status' => 'ok']);
    }
}
