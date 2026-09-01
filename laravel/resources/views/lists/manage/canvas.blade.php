<x-layouts.app :title="'Canvas: '.$page->Title.' - '.$list->list_name.' - Combo好き'">
    <x-jumbotron :height="100" />
    <x-nav-bar :game="$list->game" />

    <div class="container-fluid my-3">
        <h3>Canvas: &ldquo;{{ $page->Title }}&rdquo;</h3>
        <p class="text-white-50">Add text notes and combo nodes, drag them around, and connect them with labeled arrows.</p>

        <div class="btn-group mb-3">
            <a href="{{ route('lists.manage.index', $list) }}" class="btn btn-secondary">&larr; Back to Manage</a>
        </div>

        <div class="d-flex gap-2 mb-2">
            <button type="button" id="canvas-add-text" class="btn btn-primary">+ Text Node</button>
            <button type="button" id="canvas-add-combo" class="btn btn-primary">+ Combo Node</button>
            <button type="button" id="canvas-connect-toggle" class="btn btn-outline-light">Connect Nodes</button>
            <span id="canvas-status" class="small align-self-center"></span>
        </div>

        <div class="row">
            <div class="col-md-9">
                <div id="list-canvas-editor" style="height:65vh;background:#1a1a1a;border-radius:4px;"></div>
            </div>
            <div class="col-md-3">
                <div id="canvas-side-panel" class="card combosuki-main-reversed text-white p-3" style="min-height:200px;">
                    <p class="text-white-50 small mb-0">Click a node or edge to edit it, or click empty canvas to deselect.</p>
                </div>
            </div>
        </div>

        <x-edit-history :histories="$list->editHistories()->with('user')->limit(20)->get()" />
    </div>

    <!-- Add Text Node modal -->
    <div class="modal fade" id="canvas-text-modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content combosuki-main-reversed text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="canvas-text-modal-title">Add Text Node</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Title</label>
                        <input type="text" id="canvas-text-title" maxlength="255" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Body</label>
                        <textarea id="canvas-text-body" maxlength="2000" rows="4" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="canvas-text-save" class="btn btn-primary">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Combo Node modal -->
    <div class="modal fade" id="canvas-combo-modal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content combosuki-main-reversed text-white">
                <div class="modal-header">
                    <h5 class="modal-title">Add Combo Node</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-auto">
                            <select id="canvas-combo-character" class="form-select">
                                <option value="">Any Character</option>
                                @foreach ($characters as $character)
                                    <option value="{{ $character->idcharacter }}">{{ $character->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <select id="canvas-combo-type" class="form-select">
                                <option value="">Any Type</option>
                                @foreach ($listingTypes as $entry)
                                    <option value="{{ $entry->entryid }}">{{ $entry->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto flex-grow-1">
                            <input type="text" id="canvas-combo-search" class="form-control" placeholder="Search notation&hellip;">
                        </div>
                        <div class="col-auto align-self-center form-check">
                            <input type="checkbox" id="canvas-combo-only-guide" class="form-check-input" checked>
                            <label for="canvas-combo-only-guide" class="form-check-label">Only combos already in this guide</label>
                        </div>
                    </div>
                    <div id="canvas-combo-results" class="list-group"></div>
                </div>
            </div>
        </div>
    </div>

    <script id="canvas-editor-data" type="application/json">
        {!! json_encode([
            'nodes' => $nodes->map(fn ($node) => [
                'idCanvasNode' => $node->idCanvasNode,
                'node_type' => $node->node_type,
                'title' => $node->title,
                'body' => $node->body,
                'pos_x' => $node->pos_x,
                'pos_y' => $node->pos_y,
                'combo' => $node->combo ? [
                    'idcombo' => $node->combo->idcombo,
                    'character_name' => $node->combo->character->name,
                    'notation_html' => app(\App\Services\ComboNotationRenderer::class)->render($node->combo->character->game, $node->combo->combo),
                    'damage' => $node->combo->damage,
                    'type_title' => $node->combo->listingType?->title,
                    'url' => route('combos.show', $node->combo),
                    'video_html' => view('components.video-embed', ['video' => $node->combo->video])->render(),
                ] : null,
            ])->values(),
            'edges' => $edges->map(fn ($edge) => [
                'idCanvasEdge' => $edge->idCanvasEdge,
                'idFromNode' => $edge->idFromNode,
                'idToNode' => $edge->idToNode,
                'label' => $edge->label,
            ])->values(),
            'urls' => [
                'nodesStore' => route('lists.manage.canvas.nodes.store', [$list, $page]),
                'nodeUpdate' => route('lists.manage.canvas.nodes.update', [$list, $page, '__node__']),
                'nodeDestroy' => route('lists.manage.canvas.nodes.destroy', [$list, $page, '__node__']),
                'edgesStore' => route('lists.manage.canvas.edges.store', [$list, $page]),
                'edgeUpdate' => route('lists.manage.canvas.edges.update', [$list, $page, '__edge__']),
                'edgeDestroy' => route('lists.manage.canvas.edges.destroy', [$list, $page, '__edge__']),
                'combosSearch' => route('lists.manage.canvas.combos.search', [$list, $page]),
            ],
        ]) !!}
    </script>

    @vite(['resources/js/guide-flow-chart-editor.js'])
</x-layouts.app>
