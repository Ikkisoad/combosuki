import cytoscape from 'cytoscape';
import edgehandles from 'cytoscape-edgehandles';
import { Modal } from 'bootstrap';

cytoscape.use(edgehandles);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function postJson(url, method, body) {
    return fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    }).then((response) => response.json().then((data) => {
        if (! response.ok) {
            throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || data.error || 'Request failed.'));
        }
        return data;
    }));
}

function stripHtml(html) {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || '';
}

/**
 * Truncates a combo's notation to a short preview — the on-canvas node
 * label shows just enough to recognize the combo at a glance, not the full
 * notation (which can run to a dozen+ moves); the side panel is where the
 * full notation lives.
 */
function truncateNotation(text, max = 24) {
    const trimmed = text.trim();

    return trimmed.length > max ? trimmed.slice(0, max).trimEnd() + '…' : trimmed;
}

function comboMeta(combo) {
    const parts = [];

    if (combo?.damage) {
        parts.push(combo.damage + ' dmg');
    }
    if (combo?.type_title) {
        parts.push(combo.type_title);
    }

    return parts.join(' · ');
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
 * The small "view combo" link shown in the side panel — same icon
 * <x-combo-link> uses elsewhere on the site, scaled down with `small`
 * instead of a padded `btn` since the notation is already shown in full
 * just above it; this is just a way out to the combo's own page.
 */
function viewComboLink(url) {
    return '<a href="' + url + '" class="text-white small d-inline-block mb-2" title="View combo">'
        + '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16" class="me-1">'
        + '<path d="M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.879 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z"/>'
        + '<path d="M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.552a2 2 0 1 1 2.829 2.829l-.793.792a4 4 0 0 1 .128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243L6.586 4.672z"/>'
        + '</svg>View combo</a>';
}

document.addEventListener('DOMContentLoaded', function () {
    const mount = document.getElementById('list-canvas-editor');
    const dataEl = document.getElementById('canvas-editor-data');

    if (! mount || ! dataEl) {
        return;
    }

    const data = JSON.parse(dataEl.textContent || '{}');
    const urls = data.urls || {};
    const status = document.getElementById('canvas-status');
    const sidePanel = document.getElementById('canvas-side-panel');

    function nodeUrl(id) {
        return urls.nodeUpdate.replace('__node__', id);
    }

    function nodeDeleteUrl(id) {
        return urls.nodeDestroy.replace('__node__', id);
    }

    function edgeUrl(id) {
        return urls.edgeUpdate.replace('__edge__', id);
    }

    function edgeDeleteUrl(id) {
        return urls.edgeDestroy.replace('__edge__', id);
    }

    function setStatus(text, isError) {
        status.textContent = text;
        status.className = 'small align-self-center ' + (isError ? 'text-danger' : 'text-success');
        if (text) {
            setTimeout(() => { status.textContent = ''; }, 3000);
        }
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

    function toElement(node) {
        return {
            data: { id: 'n' + node.idCanvasNode, label: nodeLabel(node), type: node.node_type, node },
            position: { x: node.pos_x, y: node.pos_y },
        };
    }

    function toEdgeElement(edge) {
        return {
            data: {
                id: 'e' + edge.idCanvasEdge,
                source: 'n' + edge.idFromNode,
                target: 'n' + edge.idToNode,
                label: edge.label || '',
                edge,
            },
        };
    }

    const elements = [
        ...(data.nodes || []).map(toElement),
        ...(data.edges || []).map(toEdgeElement),
    ];

    const cy = cytoscape({
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
            {
                selector: '.eh-handle',
                style: {
                    'background-color': '#ffc107',
                    width: 12,
                    height: 12,
                },
            },
        ],
        layout: { name: 'preset' },
    });

    cy.fit(cy.elements(), 30);

    const eh = cy.edgehandles({
        canConnect: (source, target) => source.id() !== target.id(),
        edgeParams: () => ({ data: { label: '' } }),
    });

    document.getElementById('canvas-connect-toggle').addEventListener('click', function (event) {
        const button = event.target;
        const enabling = button.dataset.enabled !== '1';

        if (enabling) {
            eh.enableDrawMode();
            button.dataset.enabled = '1';
            button.classList.add('btn-warning');
            button.classList.remove('btn-outline-light');
        } else {
            eh.disableDrawMode();
            button.dataset.enabled = '0';
            button.classList.remove('btn-warning');
            button.classList.add('btn-outline-light');
        }
    });

    cy.on('ehcomplete', (event, sourceNode, targetNode, addedEdge) => {
        const idFromNode = Number(sourceNode.id().slice(1));
        const idToNode = Number(targetNode.id().slice(1));

        cy.remove(addedEdge);

        postJson(urls.edgesStore, 'POST', { idFromNode, idToNode })
            .then(({ edge }) => {
                const newEdge = cy.add(toEdgeElement(edge));
                setStatus('Edge added.', false);
                selectEdge(newEdge);
            })
            .catch((error) => setStatus(error.message, true));
    });

    let dragTimers = new Map();

    cy.on('dragfree', 'node', (event) => {
        const node = event.target;
        const id = Number(node.id().slice(1));
        const pos = node.position();

        clearTimeout(dragTimers.get(id));
        dragTimers.set(id, setTimeout(() => {
            postJson(nodeUrl(id), 'PATCH', { pos_x: pos.x, pos_y: pos.y })
                .then(() => setStatus('Position saved.', false))
                .catch((error) => setStatus(error.message, true));
        }, 500));
    });

    function clearPanel() {
        sidePanel.innerHTML = '<p class="text-white-50 small mb-0">Click a node or edge to edit it, or click empty canvas to deselect.</p>';
    }

    function selectNode(ele) {
        const node = ele.data('node');

        if (node.node_type === 'text') {
            sidePanel.innerHTML = `
                <label class="form-label">Title</label>
                <input type="text" id="panel-title" maxlength="255" class="form-control mb-2" value="${escapeAttr(node.title || '')}">
                <label class="form-label">Body</label>
                <textarea id="panel-body" maxlength="2000" rows="5" class="form-control mb-2">${escapeHtml(node.body || '')}</textarea>
                <div class="d-flex gap-2">
                    <button type="button" id="panel-save" class="btn btn-primary btn-sm">Save</button>
                    <button type="button" id="panel-delete" class="btn btn-danger btn-sm">Delete Node</button>
                </div>
            `;

            document.getElementById('panel-save').addEventListener('click', () => {
                const title = document.getElementById('panel-title').value;
                const body = document.getElementById('panel-body').value;

                postJson(nodeUrl(node.idCanvasNode), 'PATCH', { title, body })
                    .then(({ node: updated }) => {
                        ele.data('node', updated);
                        ele.data('label', nodeLabel(updated));
                        setStatus('Node saved.', false);
                    })
                    .catch((error) => setStatus(error.message, true));
            });
        } else {
            const meta = comboMeta(node.combo);

            sidePanel.innerHTML = `
                <h6>${escapeHtml(node.combo?.character_name || '')}</h6>
                ${meta ? `<p class="text-white-50 small mb-1">${escapeHtml(meta)}</p>` : ''}
                <p class="combo-notation">${node.combo?.notation_html || ''}</p>
                ${node.combo?.video_html || ''}
                ${viewComboLink(node.combo?.url || '#')}
                <div>
                    <button type="button" id="panel-delete" class="btn btn-danger btn-sm">Remove Node</button>
                </div>
            `;

            executeScripts(sidePanel);
        }

        document.getElementById('panel-delete').addEventListener('click', () => {
            window.confirmDialog('Delete this node? Any connected edges are removed too.').then((ok) => {
                if (! ok) {
                    return;
                }

                postJson(nodeDeleteUrl(node.idCanvasNode), 'POST')
                    .then(() => {
                        cy.remove(ele);
                        clearPanel();
                        setStatus('Node deleted.', false);
                    })
                    .catch((error) => setStatus(error.message, true));
            });
        });
    }

    function selectEdge(ele) {
        const edge = ele.data('edge') || { idCanvasEdge: Number(ele.id().slice(1)) };

        sidePanel.innerHTML = `
            <label class="form-label">Edge label</label>
            <input type="text" id="panel-edge-label" maxlength="255" class="form-control mb-2" value="${escapeAttr(ele.data('label') || '')}">
            <div class="d-flex gap-2">
                <button type="button" id="panel-edge-save" class="btn btn-primary btn-sm">Save</button>
                <button type="button" id="panel-edge-delete" class="btn btn-danger btn-sm">Delete Edge</button>
            </div>
        `;

        document.getElementById('panel-edge-save').addEventListener('click', () => {
            const label = document.getElementById('panel-edge-label').value;

            postJson(edgeUrl(edge.idCanvasEdge), 'PATCH', { label })
                .then(({ edge: updated }) => {
                    ele.data('edge', updated);
                    ele.data('label', updated.label || '');
                    setStatus('Edge saved.', false);
                })
                .catch((error) => setStatus(error.message, true));
        });

        document.getElementById('panel-edge-delete').addEventListener('click', () => {
            window.confirmDialog('Delete this edge?').then((ok) => {
                if (! ok) {
                    return;
                }

                postJson(edgeDeleteUrl(edge.idCanvasEdge), 'POST')
                    .then(() => {
                        cy.remove(ele);
                        clearPanel();
                        setStatus('Edge deleted.', false);
                    })
                    .catch((error) => setStatus(error.message, true));
            });
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeAttr(text) {
        return escapeHtml(text).replace(/"/g, '&quot;');
    }

    cy.on('tap', 'node', (event) => selectNode(event.target));
    cy.on('tap', 'edge', (event) => selectEdge(event.target));
    cy.on('tap', (event) => {
        if (event.target === cy) {
            clearPanel();
        }
    });

    // Add Text Node
    const textModalEl = document.getElementById('canvas-text-modal');
    const textModal = new Modal(textModalEl);

    document.getElementById('canvas-add-text').addEventListener('click', () => {
        document.getElementById('canvas-text-title').value = '';
        document.getElementById('canvas-text-body').value = '';
        textModal?.show();
    });

    document.getElementById('canvas-text-save').addEventListener('click', () => {
        const title = document.getElementById('canvas-text-title').value.trim();
        const body = document.getElementById('canvas-text-body').value;

        if (! title) {
            return;
        }

        const extent = cy.extent();
        const pos_x = (extent.x1 + extent.x2) / 2;
        const pos_y = (extent.y1 + extent.y2) / 2;

        postJson(urls.nodesStore, 'POST', { node_type: 'text', title, body, pos_x, pos_y })
            .then(({ node }) => {
                cy.add(toElement(node));
                textModal.hide();
                setStatus('Text node added.', false);
            })
            .catch((error) => setStatus(error.message, true));
    });

    // Add Combo Node
    const comboModalEl = document.getElementById('canvas-combo-modal');
    const comboModal = new Modal(comboModalEl);
    const comboSearch = document.getElementById('canvas-combo-search');
    const comboCharacter = document.getElementById('canvas-combo-character');
    const comboType = document.getElementById('canvas-combo-type');
    const comboOnlyGuide = document.getElementById('canvas-combo-only-guide');
    const comboResults = document.getElementById('canvas-combo-results');
    let comboSearchTimer = null;

    document.getElementById('canvas-add-combo').addEventListener('click', () => {
        comboSearch.value = '';
        comboCharacter.value = '';
        comboType.value = '';
        comboOnlyGuide.checked = true;
        comboResults.innerHTML = '';
        comboModal.show();
        runComboSearch();
    });

    comboSearch.addEventListener('input', () => {
        clearTimeout(comboSearchTimer);
        comboSearchTimer = setTimeout(() => runComboSearch(), 300);
    });

    [comboCharacter, comboType, comboOnlyGuide].forEach((el) => {
        el.addEventListener('change', () => runComboSearch());
    });

    function runComboSearch() {
        const params = new URLSearchParams();
        if (comboSearch.value) {
            params.set('combo', comboSearch.value);
        }
        if (comboCharacter.value) {
            params.set('characterid', comboCharacter.value);
        }
        if (comboType.value) {
            params.set('listingtype', comboType.value);
        }
        if (comboOnlyGuide.checked) {
            params.set('only_in_guide', '1');
        }

        fetch(urls.combosSearch + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((result) => {
                if (result.error) {
                    comboResults.innerHTML = `<p class="text-danger small mb-0">${escapeHtml(result.error)}</p>`;
                    return;
                }

                comboResults.innerHTML = (result.combos || []).map((combo) => `
                    <button type="button" class="list-group-item list-group-item-action combo-result" data-id="${combo.idcombo}">
                        <div class="text-white-50 small">${escapeHtml(combo.character_name)}</div>
                        <div class="combo-notation">${combo.notation_html}</div>
                    </button>
                `).join('') || '<p class="text-white-50 small mb-0">No combos found.</p>';

                comboResults.querySelectorAll('.combo-result').forEach((button) => {
                    button.addEventListener('click', () => {
                        const extent = cy.extent();
                        const pos_x = (extent.x1 + extent.x2) / 2;
                        const pos_y = (extent.y1 + extent.y2) / 2;

                        postJson(urls.nodesStore, 'POST', { node_type: 'combo', idCombo: Number(button.dataset.id), pos_x, pos_y })
                            .then(({ node }) => {
                                cy.add(toElement(node));
                                comboModal.hide();
                                setStatus('Combo node added.', false);
                            })
                            .catch((error) => setStatus(error.message, true));
                    });
                });
            })
            .catch(() => {
                comboResults.innerHTML = '<p class="text-danger small mb-0">Search failed.</p>';
            });
    }
});
