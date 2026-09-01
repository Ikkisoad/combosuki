import cytoscape from 'cytoscape';

let cy = null;

/**
 * Builds a read-only view of a guide's canvas page. Runs on the initial
 * (server-rendered) page load and again every time lists-show.js swaps in a
 * different page's content via AJAX — see the `list-canvas:loaded` dispatch
 * in resources/js/lists-show.js, mirroring the tab lazy-load pattern used by
 * combo-flow-chart.js. No-ops whenever the current page isn't a canvas page.
 */
function initCanvas() {
    const mount = document.getElementById('list-canvas-view');
    const dataEl = document.getElementById('list-canvas-data');

    if (cy) {
        cy.destroy();
        cy = null;
    }

    if (! mount || ! dataEl) {
        return;
    }

    const data = JSON.parse(dataEl.textContent || '{}');
    const nodes = data.nodes || [];
    const edges = data.edges || [];

    const elements = [
        ...nodes.map((node) => ({
            data: {
                id: 'n' + node.idCanvasNode,
                label: nodeLabel(node),
                type: node.node_type,
                node,
            },
            position: { x: node.pos_x, y: node.pos_y },
        })),
        ...edges.map((edge) => ({
            data: {
                id: 'e' + edge.idFromNode + '-' + edge.idToNode + '-' + (edge.label || ''),
                source: 'n' + edge.idFromNode,
                target: 'n' + edge.idToNode,
                label: edge.label || '',
            },
        })),
    ];

    cy = cytoscape({
        container: mount,
        elements,
        style: [
            {
                selector: 'node',
                style: {
                    label: 'data(label)',
                    color: '#fff',
                    'background-color': (ele) => (ele.data('type') === 'combo' ? '#0d6efd' : '#6c757d'),
                    'text-valign': 'center',
                    'text-halign': 'center',
                    'text-wrap': 'wrap',
                    'text-max-width': '140px',
                    'font-size': 12,
                    width: 150,
                    height: 60,
                    padding: '8px',
                    shape: 'round-rectangle',
                },
            },
            {
                selector: 'edge',
                style: {
                    label: 'data(label)',
                    width: 2,
                    'line-color': '#666',
                    'target-arrow-color': '#666',
                    'target-arrow-shape': 'triangle',
                    'curve-style': 'bezier',
                    color: '#ccc',
                    'font-size': 10,
                },
            },
        ],
        layout: { name: 'preset' },
    });

    cy.fit(cy.elements(), 30);

    cy.on('tap', 'node', (event) => {
        showDetails(event.target.data('node'));
    });
    cy.on('tap', (event) => {
        if (event.target === cy) {
            showDetails(null);
        }
    });
}

function stripHtml(html) {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

/**
 * <script> tags set via innerHTML never execute (a DOM/HTML5 restriction),
 * so the video embed's own script-based providers — Twitter's widgets.js,
 * Imgur's embed.js, a Nicovideo inline `<script>` — would otherwise render
 * as inert markup. Re-creating each one as a fresh element forces the
 * browser to actually run it, the same trick <x-video-embed> gets "for
 * free" when Blade renders it directly into the initial page HTML.
 */
function executeScripts(container) {
    container.querySelectorAll('script').forEach((oldScript) => {
        const newScript = document.createElement('script');

        for (const attr of oldScript.attributes) {
            newScript.setAttribute(attr.name, attr.value);
        }

        newScript.textContent = oldScript.textContent;
        oldScript.replaceWith(newScript);
    });
}

/**
 * The small "view combo" link shown in the canvas details panel — same
 * icon <x-combo-link> uses elsewhere on the site, scaled down with `small`
 * instead of a padded `btn` since the notation is already shown in full
 * just above it; this is just a way out to the combo's own page.
 */
function viewComboLink(url) {
    return '<a href="' + url + '" class="text-white small" title="View combo">'
        + '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16" class="me-1">'
        + '<path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.879 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z"/>'
        + '<path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.552a2 2 0 1 1 2.829 2.829l-.793.792a4 4 0 0 1 .128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243L6.586 4.672z"/>'
        + '</svg>View combo</a>';
}

/**
 * Matches the `number_format($damage, 0, '', '.')` formatting combo
 * listings/show pages use elsewhere on the site (period-grouped thousands,
 * no decimal) — 'de-DE' groups the same way English text otherwise reads.
 */
function formatDamage(damage) {
    return new Intl.NumberFormat('de-DE').format(damage);
}

/**
 * Truncates a combo's notation to a short preview — the on-canvas node
 * label shows just enough to recognize the combo at a glance, not the full
 * notation (which can run to a dozen+ moves); the details panel/link is
 * where the full notation lives.
 */
function truncateNotation(text, max = 24) {
    const trimmed = text.trim();

    return trimmed.length > max ? trimmed.slice(0, max).trimEnd() + '…' : trimmed;
}

function comboMeta(combo) {
    const parts = [];

    if (combo?.damage) {
        parts.push(formatDamage(combo.damage) + ' dmg');
    }
    if (combo?.type_title) {
        parts.push(combo.type_title);
    }

    return parts.join(' · ');
}

function nodeLabel(node) {
    if (node.node_type !== 'combo') {
        return node.title || '';
    }

    const lines = [node.combo?.character_name || '', truncateNotation(stripHtml(node.combo?.notation_html || ''))];
    const meta = comboMeta(node.combo);

    if (meta) {
        lines.push(meta);
    }

    return lines.join('\n');
}

function showDetails(node) {
    const panel = document.getElementById('list-canvas-details');

    if (! panel) {
        return;
    }

    if (! node) {
        panel.innerHTML = '<p class="text-white-50 small mb-0">Click a node to see its details.</p>';
        return;
    }

    if (node.node_type === 'combo' && node.combo) {
        const meta = comboMeta(node.combo);

        panel.innerHTML = '<h5 class="mb-1">' + escapeHtml(node.combo.character_name) + '</h5>'
            + (meta ? '<p class="text-white-50 small mb-2">' + escapeHtml(meta) + '</p>' : '')
            + '<p class="combo-notation mb-2">' + node.combo.notation_html + '</p>'
            + (node.combo.video_html || '')
            + viewComboLink(node.combo.url);

        executeScripts(panel);
    } else {
        panel.innerHTML = '<h5 class="mb-2">' + escapeHtml(node.title) + '</h5>'
            + '<p class="mb-0" style="white-space:pre-wrap;">' + escapeHtml(node.body) + '</p>';
    }
}

document.addEventListener('DOMContentLoaded', initCanvas);
document.addEventListener('list-canvas:loaded', initCanvas);
