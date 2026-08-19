<x-layouts.app title="Admin Dashboard">
    <x-nav-bar />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <h1 class="text-white">Admin Dashboard</h1>
        <p class="text-white">
            Search, select, and bulk-delete spam or unwanted entries across content types.
            <a href="{{ route('admin.users.index') }}" class="link-light">Manage Users &rarr;</a>
        </p>

        <form method="get" action="{{ route('admin.dashboard') }}" class="card combosuki-main-reversed text-white p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label">Search Combos</label>
                    <input type="text" name="combo_search" class="form-control" value="{{ request('combo_search') }}" placeholder="notation, author, comments">
                </div>
                <div class="col-auto">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="combo_unverified" value="1" id="combo_unverified" class="form-check-input" @checked(request()->boolean('combo_unverified'))>
                        <label class="form-check-label" for="combo_unverified">Unverified only</label>
                    </div>
                </div>
                <div class="col-auto">
                    <label class="form-label">Search Lists</label>
                    <input type="text" name="list_search" class="form-control" value="{{ request('list_search') }}" placeholder="list name">
                </div>
                <div class="col-auto">
                    <label class="form-label">Search Games</label>
                    <input type="text" name="game_search" class="form-control" value="{{ request('game_search') }}" placeholder="game name">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <form method="post" action="{{ route('admin.dashboard.destroy') }}" id="bulk-delete-form">
            @csrf

            <h2 class="text-white h4">Combos <small class="text-white-50">({{ $combos->total() }})</small></h2>

            @if (request()->filled('combo_search'))
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="combo-select-all-matching">
                    <label class="form-check-label text-white" for="combo-select-all-matching">
                        Select all {{ $combos->total() }} combos matching &ldquo;{{ request('combo_search') }}&rdquo;{{ request()->boolean('combo_unverified') ? ' (unverified only)' : '' }} &mdash; not just this page
                    </label>
                </div>
                <input type="hidden" name="combo_delete_all_matching" value="1" id="combo-delete-all-matching-input" disabled>
                <input type="hidden" name="combo_search" value="{{ request('combo_search') }}" id="combo-search-hidden-input" disabled>
                @if (request()->boolean('combo_unverified'))
                    <input type="hidden" name="combo_unverified" value="1" id="combo-unverified-hidden-input" disabled>
                @endif
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle combosuki-main-reversed text-white">
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="form-check-input select-all" data-target=".combo-checkbox"></th>
                            <th>ID</th>
                            <th>Character</th>
                            <th>Notation</th>
                            <th>Author</th>
                            <th>Submitted</th>
                            <th>Verified</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($combos as $combo)
                            <tr>
                                <td><input type="checkbox" name="combo_ids[]" value="{{ $combo->idcombo }}" class="form-check-input combo-checkbox"></td>
                                <td>{{ $combo->idcombo }}</td>
                                <td>{{ $combo->character?->name }} <span class="text-white-50">({{ $combo->character?->game?->name }})</span></td>
                                <td class="text-truncate" style="max-width: 320px;" title="{{ $combo->combo }}">{{ $combo->combo }}</td>
                                <td>{{ $combo->author ?: $combo->user?->nickname ?: '—' }}</td>
                                <td>{{ $combo->submited?->format('Y-m-d') }}</td>
                                <td>{{ $combo->verified ? 'Yes' : 'No' }}</td>
                                <td><a href="{{ route('combos.show', $combo) }}" class="btn btn-sm btn-outline-light" target="_blank" rel="noopener">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8">No combos found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $combos->links() }}

            <h2 class="text-white h4 mt-4">Lists <small class="text-white-50">({{ $lists->total() }})</small></h2>
            <div class="table-responsive">
                <table class="table table-hover align-middle combosuki-main-reversed text-white">
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="form-check-input select-all" data-target=".list-checkbox"></th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Game</th>
                            <th>Owner</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lists as $list)
                            <tr>
                                <td><input type="checkbox" name="list_ids[]" value="{{ $list->idlist }}" class="form-check-input list-checkbox"></td>
                                <td>{{ $list->idlist }}</td>
                                <td>{{ $list->list_name }}</td>
                                <td>{{ $list->game?->name ?: '—' }}</td>
                                <td>{{ $list->user?->nickname ?: '—' }}</td>
                                <td>{{ $list->created_at?->format('Y-m-d') }}</td>
                                <td><a href="{{ route('lists.show', $list) }}" class="btn btn-sm btn-outline-light" target="_blank" rel="noopener">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No lists found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $lists->links() }}

            <h2 class="text-white h4 mt-4">Games <small class="text-white-50">({{ $games->total() }})</small></h2>
            <p class="text-white-50">Deleting a game deletes everything submitted on it: its characters, all of their combos, buttons, resources, links, entry types, and its own lists.</p>
            <div class="table-responsive">
                <table class="table table-hover align-middle combosuki-main-reversed text-white">
                    <thead>
                        <tr>
                            <th><input type="checkbox" class="form-check-input select-all" data-target=".game-checkbox"></th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Characters</th>
                            <th>Lists</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($games as $game)
                            <tr>
                                <td><input type="checkbox" name="game_ids[]" value="{{ $game->idgame }}" class="form-check-input game-checkbox"></td>
                                <td>{{ $game->idgame }}</td>
                                <td>{{ $game->name }}</td>
                                <td>{{ $game->characters_count }}</td>
                                <td>{{ $game->lists_count }}</td>
                                <td><a href="{{ route('games.show', $game) }}" class="btn btn-sm btn-outline-light" target="_blank" rel="noopener">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No games found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $games->links() }}

            <div class="sticky-bottom bg-dark p-3 mt-3 border-top border-secondary d-flex align-items-center gap-3">
                <span class="text-white"><span id="selected-count">0</span> selected</span>
                <button type="submit" class="btn btn-danger" id="delete-selected-btn" disabled>
                    Delete Selected
                </button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const form = document.getElementById('bulk-delete-form');
            const countEl = document.getElementById('selected-count');
            const deleteBtn = document.getElementById('delete-selected-btn');
            const selectAllMatching = document.getElementById('combo-select-all-matching');
            const comboTotal = {{ $combos->total() }};

            function deleteAllMatchingActive() {
                return selectAllMatching && selectAllMatching.checked;
            }

            function updateCount() {
                if (deleteAllMatchingActive()) {
                    countEl.textContent = comboTotal;
                    deleteBtn.disabled = comboTotal === 0;
                    return;
                }

                const checked = form.querySelectorAll('input[type=checkbox][name$="_ids[]"]:checked').length;
                countEl.textContent = checked;
                deleteBtn.disabled = checked === 0;
            }

            form.querySelectorAll('.select-all').forEach(function (selectAll) {
                selectAll.addEventListener('change', function () {
                    form.querySelectorAll(selectAll.dataset.target).forEach(function (checkbox) {
                        checkbox.checked = selectAll.checked;
                    });
                    updateCount();
                });
            });

            form.addEventListener('change', function (event) {
                if (event.target.matches('input[type=checkbox][name$="_ids[]"]')) {
                    updateCount();
                }
            });

            if (selectAllMatching) {
                selectAllMatching.addEventListener('change', function () {
                    const active = selectAllMatching.checked;

                    ['combo-delete-all-matching-input', 'combo-search-hidden-input', 'combo-unverified-hidden-input'].forEach(function (id) {
                        const el = document.getElementById(id);
                        if (el) el.disabled = !active;
                    });

                    // Deleting every matching combo already covers whatever is
                    // checked on the current page, so disable those checkboxes
                    // to avoid implying they're being counted separately.
                    form.querySelectorAll('.combo-checkbox').forEach(function (checkbox) {
                        checkbox.disabled = active;
                        if (active) checkbox.checked = false;
                    });
                    const comboSelectAllHeader = form.querySelector('.select-all[data-target=".combo-checkbox"]');
                    if (comboSelectAllHeader) {
                        comboSelectAllHeader.disabled = active;
                        if (active) comboSelectAllHeader.checked = false;
                    }

                    updateCount();
                });
            }

            form.addEventListener('submit', function (event) {
                const games = form.querySelectorAll('.game-checkbox:checked').length;

                let message;
                if (deleteAllMatchingActive()) {
                    message = `Permanently delete ALL ${comboTotal} combos matching "{{ request('combo_search') }}"? This cannot be undone.`;
                } else {
                    const total = form.querySelectorAll('input[type=checkbox][name$="_ids[]"]:checked').length;
                    message = games > 0
                        ? `Permanently delete ${total} selected entries, including ${games} game(s)? Deleting a game also deletes all of its characters, combos, buttons, resources, links, entry types, and lists. This cannot be undone.`
                        : `Permanently delete ${total} selected entries? This cannot be undone.`;
                }

                if (!confirm(message)) {
                    event.preventDefault();
                }
            });
        })();
    </script>
</x-layouts.app>
