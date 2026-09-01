<?php

namespace App\Http\Controllers;

use App\Models\ListModel;
use App\Models\ListPage;
use App\Models\ListPageCanvasEdge;
use App\Models\ListPageCanvasNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ListCanvasEdgeController extends Controller
{
    public function store(Request $request, ListModel $list, ListPage $page): JsonResponse
    {
        $this->authorize('update', $list);

        abort_if($page->idList !== $list->idlist || ! $page->isCanvas(), 404);

        $nodeIds = $page->canvasNodes()->pluck('idCanvasNode');

        $validated = $request->validate([
            'idFromNode' => ['required', 'integer', Rule::in($nodeIds)],
            'idToNode' => ['required', 'integer', Rule::in($nodeIds)],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $edge = ListPageCanvasEdge::create($validated);
        $list->recordEdit();

        return response()->json(['edge' => $this->serialize($edge)], 201);
    }

    public function update(Request $request, ListModel $list, ListPage $page, ListPageCanvasEdge $edge): JsonResponse
    {
        $this->authorize('update', $list);

        abort_if($page->idList !== $list->idlist || ! $this->edgeBelongsToPage($edge, $page), 404);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $edge->update($validated);
        $list->recordEdit();

        return response()->json(['edge' => $this->serialize($edge)]);
    }

    public function destroy(ListModel $list, ListPage $page, ListPageCanvasEdge $edge): JsonResponse
    {
        $this->authorize('update', $list);

        abort_if($page->idList !== $list->idlist || ! $this->edgeBelongsToPage($edge, $page), 404);

        $edge->delete();
        $list->recordEdit('deleted');

        return response()->json(['status' => 'ok']);
    }

    private function edgeBelongsToPage(ListPageCanvasEdge $edge, ListPage $page): bool
    {
        return ListPageCanvasNode::where('idCanvasNode', $edge->idFromNode)
            ->where('idListPage', $page->idListPage)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ListPageCanvasEdge $edge): array
    {
        return [
            'idCanvasEdge' => $edge->idCanvasEdge,
            'idFromNode' => $edge->idFromNode,
            'idToNode' => $edge->idToNode,
            'label' => $edge->label,
        ];
    }
}
