@props(['nodes', 'edges'])

<div id="list-canvas-view" style="height:60vh;background:#1a1a1a;border-radius:4px;"></div>

<div id="list-canvas-details" class="card combosuki-main-reversed text-white p-3 mt-3">
    <p class="text-white-50 small mb-0">Click a node to see its details.</p>
</div>

<script id="list-canvas-data" type="application/json">
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
        'idFromNode' => $edge->idFromNode,
        'idToNode' => $edge->idToNode,
        'label' => $edge->label,
    ])->values(),
]) !!}
</script>
