import cytoscape from 'cytoscape';

const START = '__start__';

/**
 * Builds the interactive combo flow chart the first time its data island
 * shows up in the DOM. The flow-chart tab pane is fetched lazily (see
 * characters/show.blade.php), so this listens for the custom event that
 * fires right after that fetch injects the partial's markup, rather than
 * running on DOMContentLoaded when the mount point wouldn't exist yet.
 */
function initFlowChart() {
    const resultsContainer = document.getElementById('flow-chart-results');
    const filterForm = document.getElementById('flow-chart-filters');

    // Wired up even when the chart itself has nothing to show (no combos
    // match the current filters) so the filter form stays usable — it's
    // rendered on every response, not just ones with a chart.
    if (filterForm && resultsContainer) {
        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();

            const params = new URLSearchParams(new FormData(filterForm));
            const endpoint = resultsContainer.dataset.endpoint;

            resultsContainer.innerHTML = '<p class="text-white-50">Loading&hellip;</p>';

            fetch(endpoint + '?' + params.toString())
                .then((response) => response.text())
                .then((html) => {
                    resultsContainer.innerHTML = html;
                    // Re-triggers this whole function against the freshly
                    // rendered markup, same as the initial lazy-load in
                    // characters/show.blade.php — a filter change rebuilds
                    // the chart from a different combo set, so everything
                    // (cytoscape instance, selected path) needs to start
                    // over rather than trying to patch the old one.
                    document.dispatchEvent(new CustomEvent('combo-flow-chart:loaded'));
                })
                .catch(() => {
                    resultsContainer.innerHTML = '<p class="text-danger">Failed to load the combo flow chart.</p>';
                });
        });
    }

    const mount = document.getElementById('combo-flow-chart');
    const dataEl = document.getElementById('flow-chart-data');
    const output = document.getElementById('flow-chart-output');
    const resetButton = document.getElementById('flow-chart-reset');
    const matchesEl = document.getElementById('flow-chart-matches');

    if (! mount || ! dataEl) {
        return;
    }

    // The moves that can open a combo (path []), embedded by the server so
    // the very first paint doesn't need an extra round trip. Every step
    // after this one is fetched fresh — see loadOptionsForCurrentPath().
    const initialOptions = (JSON.parse(dataEl.textContent || '{}').moves) || [];
    const nextEndpoint = mount.dataset.nextEndpoint;

    const cy = cytoscape({
        container: mount,
        elements: [{ data: { id: START, label: 'START' } }],
        style: [
            {
                selector: 'node',
                style: {
                    label: 'data(label)',
                    'background-color': (ele) => ele.data('color') || '#6c757d',
                    color: '#fff',
                    'text-valign': 'center',
                    'text-halign': 'center',
                    'font-size': 12,
                    width: (ele) => 16 + String(ele.data('label')).length * 6.5,
                    height: 30,
                    padding: '8px',
                    shape: 'round-rectangle',
                },
            },
            {
                selector: `#${START}`,
                style: {
                    'background-color': '#0d6efd',
                    shape: 'ellipse',
                },
            },
            {
                selector: 'edge',
                style: {
                    label: 'data(count)',
                    width: (ele) => Math.min(1 + Math.log2(ele.data('count') + 1), 8),
                    'line-color': '#666',
                    'target-arrow-color': '#666',
                    'target-arrow-shape': 'triangle',
                    'curve-style': 'bezier',
                    color: '#ccc',
                    'font-size': 10,
                },
            },
            {
                selector: '.flow-chart-selected-node',
                style: { 'border-width': 3, 'border-color': '#ffc107' },
            },
            {
                selector: '.flow-chart-selected-edge',
                style: { 'line-color': '#ffc107', 'target-arrow-color': '#ffc107', width: 4 },
            },
            {
                selector: '.flow-chart-current-node',
                style: { 'border-width': 3, 'border-color': '#20c997' },
            },
        ],
        layout: { name: 'preset' },
    });

    let selectedPath = [START];
    let currentOptions = [];
    let isLoadingOptions = false;

    function currentNode() {
        return selectedPath[selectedPath.length - 1];
    }

    /**
     * Swaps in a fresh set of next-step options: removes whichever nodes
     * from the *previous* step aren't part of the path (the ones the user
     * didn't pick), adds the new ones plus an edge from the current node to
     * each, then repositions everything. Cytoscape only ever holds the path
     * built so far and the current step's options — nothing else — so
     * there's no separate "hide the rest" bookkeeping to keep in sync with
     * what these options actually are for the current path.
     */
    function applyOptions(options) {
        currentOptions = options;

        cy.nodes()
            .filter((node) => node.id() !== START && selectedPath.indexOf(node.id()) === -1)
            .remove();

        const current = currentNode();
        const newElements = [];

        options.forEach((option) => {
            if (cy.getElementById(option.key).empty()) {
                newElements.push({ data: { id: option.key, label: option.label, color: option.color } });
            }

            newElements.push({
                data: {
                    id: current + '->' + option.key,
                    source: current,
                    target: option.key,
                    count: option.count,
                },
            });
        });

        cy.add(newElements);

        refreshView();
    }

    /**
     * Positions the path built so far in one straight left-to-right line
     * (so START always anchors the far left) and the current step's options
     * fanned out in a column right after it, most-observed first.
     */
    function refreshView() {
        const spacingX = 150;
        const spacingY = 44;
        const positions = {};

        selectedPath.forEach((id, index) => {
            positions[id] = { x: index * spacingX, y: 0 };
        });

        const sorted = [...currentOptions].sort((a, b) => b.count - a.count);
        const startY = -((sorted.length - 1) * spacingY) / 2;
        sorted.forEach((option, index) => {
            positions[option.key] = { x: selectedPath.length * spacingX, y: startY + index * spacingY };
        });

        cy.elements().removeClass('flow-chart-selected-node flow-chart-selected-edge flow-chart-current-node');

        selectedPath.forEach((id) => {
            cy.getElementById(id).addClass('flow-chart-selected-node');
        });
        cy.getElementById(currentNode()).addClass('flow-chart-current-node');

        for (let i = 1; i < selectedPath.length; i += 1) {
            const from = selectedPath[i - 1];
            const to = selectedPath[i];
            cy.edges().forEach((edge) => {
                if (edge.data('source') === from && edge.data('target') === to) {
                    edge.addClass('flow-chart-selected-edge');
                }
            });
        }

        // No animation: a preset layout applies positions synchronously, so
        // fitting right after run() is simpler and more reliable than the
        // layout's own `fit` option, which only honored the very first run.
        cy.layout({
            name: 'preset',
            positions: (node) => positions[node.id()] || { x: 0, y: 0 },
        }).run();

        cy.fit(cy.elements(), 30);
    }

    function refreshOutput() {
        const labels = selectedPath.slice(1).map((id) => cy.getElementById(id).data('label'));

        if (labels.length === 0) {
            output.textContent = 'Click a starting move below…';
        } else if (isLoadingOptions) {
            output.textContent = labels.join(' > ');
        } else if (currentOptions.length === 0) {
            output.textContent = labels.join(' > ') + ' — end of the line, no further moves recorded.';
        } else {
            output.textContent = labels.join(' > ');
        }
    }

    /**
     * Fetches the moves that can legitimately follow $path — i.e. only
     * moves that appear next in a real combo whose sequence starts with
     * exactly this path, not just any move that's ever followed the
     * current one somewhere. Carries the same Type/primary-resource
     * filters the chart itself was built from.
     */
    function fetchNextMoves(pathKeys) {
        const params = new URLSearchParams(filterForm ? new FormData(filterForm) : undefined);
        pathKeys.forEach((key) => params.append('path[]', key));

        return fetch(nextEndpoint + '?' + params.toString())
            .then((response) => response.json())
            .then((data) => data.moves || [])
            .catch(() => []);
    }

    let optionsRequestToken = 0;

    function loadOptionsForCurrentPath() {
        isLoadingOptions = true;
        refreshOutput();

        // Clear the previous step's options immediately so a now-invalid
        // one can't be tapped while the real ones for the new path are
        // still loading.
        applyOptions([]);

        fetchAndAutoAdvance(++optionsRequestToken);
    }

    /**
     * Fetches the moves following the current path and, as long as there's
     * exactly one, keeps walking forward on its own — a forced move isn't a
     * real decision point, so the user shouldn't have to click through it.
     * The matches lookup is deliberately skipped for these in-between hops:
     * it's a combos-matching-this-path search, and mid-chain the path isn't
     * where it's going to land yet, so searching for it here would just be
     * one throwaway request per hop. It only runs once the walk actually
     * stops, at a real end-of-line or branching node.
     */
    function fetchAndAutoAdvance(requestToken) {
        const pathKeys = selectedPath.slice(1);
        const fetcher = pathKeys.length === 0 ? Promise.resolve(initialOptions) : fetchNextMoves(pathKeys);

        fetcher.then((options) => {
            if (requestToken !== optionsRequestToken) {
                return;
            }

            // The node/edge still has to be added via applyOptions() before
            // advancing past it, or it's left on selectedPath with no
            // backing element (blank label, edges with a missing source).
            if (options.length === 1) {
                applyOptions(options);
                selectedPath.push(options[0].key);
                fetchAndAutoAdvance(requestToken);
                return;
            }

            isLoadingOptions = false;
            applyOptions(options);
            refreshOutput();
            refreshMatches();
        });
    }

    let matchesRequestToken = 0;

    function refreshMatches() {
        if (! matchesEl) {
            return;
        }

        const keys = selectedPath.slice(1);

        if (keys.length === 0) {
            matchesEl.innerHTML = '<p class="text-white-50 small mb-0">Click a move above to see which existing combos start with it.</p>';
            return;
        }

        const requestToken = ++matchesRequestToken;
        // Carries the same Type/primary-resource filters the chart itself
        // was built from, so a match search stays scoped to the filtered
        // set currently on screen rather than the character's full combo
        // list.
        const params = new URLSearchParams(filterForm ? new FormData(filterForm) : undefined);
        keys.forEach((key) => params.append('path[]', key));

        matchesEl.innerHTML = '<p class="text-white-50 small mb-0">Searching&hellip;</p>';

        fetch(matchesEl.dataset.endpoint + '?' + params.toString())
            .then((response) => response.text())
            .then((html) => {
                if (requestToken === matchesRequestToken) {
                    matchesEl.innerHTML = html;
                }
            })
            .catch(() => {
                if (requestToken === matchesRequestToken) {
                    matchesEl.innerHTML = '<p class="text-danger small mb-0">Failed to load matching combos.</p>';
                }
            });
    }

    cy.on('tap', 'node', (event) => {
        const tappedId = event.target.id();

        // Tapping a node already on the path jumps back to it (truncating
        // anything picked after it) instead of only ever accepting a step
        // forward, so a wrong turn doesn't require a full reset.
        const pathIndex = selectedPath.indexOf(tappedId);

        if (pathIndex !== -1) {
            if (pathIndex === selectedPath.length - 1) {
                return;
            }

            selectedPath = selectedPath.slice(0, pathIndex + 1);
            loadOptionsForCurrentPath();

            return;
        }

        const isValidNext = currentOptions.some((option) => option.key === tappedId);

        if (! isValidNext) {
            return;
        }

        selectedPath.push(tappedId);
        loadOptionsForCurrentPath();
    });

    if (resetButton) {
        resetButton.addEventListener('click', () => {
            selectedPath = [START];
            loadOptionsForCurrentPath();
        });
    }

    loadOptionsForCurrentPath();
}

document.addEventListener('combo-flow-chart:loaded', initFlowChart);
