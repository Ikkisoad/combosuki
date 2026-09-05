<x-layouts.app :title="'Guides - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form id="bulk-lists-form" method="post" action="{{ route('admin.lists.bulkUpdate', $game) }}">
            @csrf
        </form>

        <div class="row g-2 mb-2">
            <div class="col-sm-6 col-md-4">
                <input type="text" id="guide-filter-name" class="form-control" placeholder="Filter by name...">
            </div>
            <div class="col-sm-6 col-md-3">
                <select id="guide-filter-type" class="form-select">
                    <option value="">All types</option>
                    <option value="1">Normal</option>
                    <option value="2">Verified</option>
                    <option value="3">Featured</option>
                    <option value="0">Hidden</option>
                </select>
            </div>
        </div>

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <caption>Edit the fields below and click "Save All" to update every guide at once.</caption>
            <tr><th>Guide</th><th>Featured on character pages</th></tr>
            @foreach ($lists as $list)
                <tr class="guide-row" data-name="{{ mb_strtolower($list->list_name) }}" data-type="{{ $list->type }}">
                    <td>
                        <div class="input-group">
                            <a href="{{ route('lists.show', $list) }}" class="btn btn-secondary">{{ $list->list_name }}</a>
                            <textarea form="bulk-lists-form" name="lists[{{ $list->idlist }}][list_name]" maxlength="100" class="form-control" rows="1">{{ $list->list_name }}</textarea>
                            <select form="bulk-lists-form" name="lists[{{ $list->idlist }}][type]" class="form-select">
                                <option value="1" @selected($list->type === 1)>Normal</option>
                                <option value="2" @selected($list->type === 2)>Verified</option>
                                <option value="3" @selected($list->type === 3)>Featured</option>
                                <option value="0" @selected($list->type === 0)>Hidden</option>
                            </select>
                            <form method="post" action="{{ route('admin.lists.store', $game) }}" data-confirm="Are you sure you want to delete this list?">
                                @csrf
                                <input type="hidden" name="idlist" value="{{ $list->idlist }}">
                                <button type="submit" name="action" value="Delete" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                    <td>
                        @php $selectedCharacterIds = $list->featuredForCharacters->pluck('idcharacter')->all(); @endphp
                        <div class="d-flex align-items-start gap-2">
                            <select form="bulk-lists-form" name="lists[{{ $list->idlist }}][characters][]" class="form-select" multiple size="3">
                                @foreach ($characters as $characterOption)
                                    <option value="{{ $characterOption->idcharacter }}" @selected(in_array($characterOption->idcharacter, $selectedCharacterIds))>{{ $characterOption->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-light btn-sm" onclick="clearMultiSelect(this)">Clear</button>
                        </div>
                        <div class="form-text text-white-50">Leave empty to not feature this guide on any character page.</div>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2">
                    <button type="submit" form="bulk-lists-form" class="btn btn-primary">Save All</button>
                </td>
            </tr>
        </table>

        <x-admin.edit-nav :game="$game" />
    </div>

    <script>
        (function () {
            const nameFilter = document.getElementById('guide-filter-name');
            const typeFilter = document.getElementById('guide-filter-type');
            const rows = document.querySelectorAll('.guide-row');

            function applyFilters() {
                const name = nameFilter.value.trim().toLowerCase();
                const type = typeFilter.value;

                rows.forEach(function (row) {
                    const matchesName = !name || row.dataset.name.includes(name);
                    const matchesType = !type || row.dataset.type === type;
                    row.style.display = (matchesName && matchesType) ? '' : 'none';
                });
            }

            nameFilter.addEventListener('input', applyFilters);
            typeFilter.addEventListener('change', applyFilters);
        })();
    </script>
</x-layouts.app>
