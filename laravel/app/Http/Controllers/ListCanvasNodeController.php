<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\ListModel;
use App\Models\ListPage;
use App\Models\ListPageCanvasNode;
use App\Services\ComboNotationRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ListCanvasNodeController extends Controller
{
    public function store(Request $request, ListModel $list, ListPage $page): JsonResponse
    {
        $this->authorize('update', $list);

        abort_if($page->idList !== $list->idlist || ! $page->isCanvas(), 404);

        $validated = $request->validate([
            'node_type' => ['required', 'string', Rule::in(['text', 'combo'])],
            'title' => ['required_if:node_type,text', 'nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
            'idCombo' => ['required_if:node_type,combo', 'nullable', 'integer', 'exists:combo,idcombo'],
            'pos_x' => ['nullable', 'numeric'],
            'pos_y' => ['nullable', 'numeric'],
        ]);

        $attributes = [
            'idListPage' => $page->idListPage,
            'node_type' => $validated['node_type'],
            'pos_x' => $validated['pos_x'] ?? 0,
            'pos_y' => $validated['pos_y'] ?? 0,
        ];

        if ($validated['node_type'] === 'text') {
            $attributes['title'] = $validated['title'];
            $attributes['body'] = $validated['body'] ?? null;
        } else {
            $combo = Combo::with('character')->findOrFail($validated['idCombo']);

            if ($list->game_idgame !== null && $combo->character->game_idgame !== $list->game_idgame) {
                throw ValidationException::withMessages([
                    'idCombo' => 'That combo does not belong to this guide\'s game.',
                ]);
            }

            $attributes['idCombo'] = $combo->idcombo;
        }

        $node = ListPageCanvasNode::create($attributes);
        $node->load(['combo.character', 'combo.listingType']);
        $list->recordEdit();

        return response()->json(['node' => $this->serialize($node)], 201);
    }

    public function update(Request $request, ListModel $list, ListPage $page, ListPageCanvasNode $node): JsonResponse
    {
        $this->authorize('update', $list);

        abort_if($page->idList !== $list->idlist || $node->idListPage !== $page->idListPage, 404);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'pos_x' => ['sometimes', 'numeric'],
            'pos_y' => ['sometimes', 'numeric'],
        ]);

        $attributes = [];

        if ($node->node_type === 'text') {
            if (array_key_exists('title', $validated)) {
                $attributes['title'] = $validated['title'];
            }
            if (array_key_exists('body', $validated)) {
                $attributes['body'] = $validated['body'];
            }
        }

        if (array_key_exists('pos_x', $validated)) {
            $attributes['pos_x'] = $validated['pos_x'];
        }
        if (array_key_exists('pos_y', $validated)) {
            $attributes['pos_y'] = $validated['pos_y'];
        }

        if ($attributes !== []) {
            $node->update($attributes);
            $list->recordEdit();
        }

        return response()->json(['node' => $this->serialize($node->fresh(['combo.character', 'combo.listingType']))]);
    }

    public function destroy(ListModel $list, ListPage $page, ListPageCanvasNode $node): JsonResponse
    {
        $this->authorize('update', $list);

        abort_if($page->idList !== $list->idlist || $node->idListPage !== $page->idListPage, 404);

        $node->delete();
        $list->recordEdit('deleted');

        return response()->json(['status' => 'ok']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ListPageCanvasNode $node): array
    {
        return [
            'idCanvasNode' => $node->idCanvasNode,
            'node_type' => $node->node_type,
            'title' => $node->title,
            'body' => $node->body,
            'pos_x' => $node->pos_x,
            'pos_y' => $node->pos_y,
            'combo' => $node->combo ? [
                'idcombo' => $node->combo->idcombo,
                'character_name' => $node->combo->character->name,
                'notation_html' => app(ComboNotationRenderer::class)->render($node->combo->character->game, $node->combo->combo),
                'damage' => $node->combo->damage,
                'type_title' => $node->combo->listingType?->title,
                'url' => route('combos.show', $node->combo),
                'video_html' => view('components.video-embed', ['video' => $node->combo->video])->render(),
            ] : null,
        ];
    }
}
