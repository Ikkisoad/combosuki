<x-layouts.app :title="'Add Combos - '.$list->list_name.' - Combo好き'">
    <x-jumbotron :height="100" />
    <x-nav-bar :game="$list->game ?? null" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <h3>Add Combos to &ldquo;{{ $list->list_name }}&rdquo;</h3>
        <x-lists.manage-nav :list="$list" />

        @if ($needsGame)
            <div class="alert alert-warning">
                This list has no game set, so combos can't be browsed by character/notation. Set a game for this list first (recreate it with a game, or ask a trusted user for help), or add combos individually from the list's page.
            </div>
        @else
            @php $buttons = $game->buttons()->orderBy('order')->get(); @endphp

            <form method="get" action="{{ route('lists.manage.combos.index', $list) }}" class="card combosuki-main-reversed text-white p-3 mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label">Character</label>
                        <select name="characterid" class="form-select">
                            <option value="-">Character</option>
                            @foreach ($characters as $character)
                                <option value="{{ $character->idcharacter }}" @selected(request('characterid') == $character->idcharacter)>{{ $character->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Type</label>
                        <select name="listingtype" class="form-select">
                            <option value="-" @selected(request('listingtype', '-') === '-')>Show All</option>
                            @foreach ($listingTypes as $entry)
                                <option value="{{ $entry->entryid }}" @selected(request('listingtype') == $entry->entryid)>{{ $entry->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label">The Combo</label>
                        <select name="combolike" class="form-select">
                            <option value="0" @selected(request('combolike', '0') === '0')>Starts with</option>
                            <option value="2" @selected(request('combolike') === '2')>Has</option>
                            <option value="1" @selected(request('combolike') === '1')>Ends with</option>
                            <option value="3" @selected(request('combolike') === '3')>Does not have</option>
                        </select>
                    </div>
                    <div class="col-auto flex-grow-1">
                        <label class="form-label">Notation</label>
                        <textarea name="combo" id="comboarea" class="form-control" rows="1">{{ request('combo') }}</textarea>
                    </div>
                </div>

                <div class="mt-2">
                    @foreach ($buttons as $button)
                        <button type="button" class="btn btn-sm" style="margin-left:0.25em;margin-bottom:0.5em;background-color: {{ $button->color }};" onclick="moveNumbers('{{ $button->name }}')">{{ $button->name }}</button>
                    @endforeach
                    <button type="button" class="btn btn-sm btn-secondary" onclick="backspace()">&#9003; Backspace</button>
                </div>

                <div class="row g-2 align-items-end mt-2">
                    <div class="col-auto">
                        <label class="form-label">Max Damage</label>
                        <input type="number" name="damage" class="form-control" value="{{ request('damage') }}">
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Comment has (# separated)</label>
                        <input type="text" name="comments" class="form-control" placeholder="#universal #corner" value="{{ request('comments') }}">
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-info">Search</button>
                </div>
            </form>

            <form method="post" action="{{ route('lists.manage.combos.store', $list) }}" id="bulk-add-form">
                @csrf

                <div class="d-flex gap-2 align-items-end mb-2">
                    <div>
                        <label class="form-label">Add selected combos to</label>
                        <select name="category_id" class="form-select">
                            <option value="">No Category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->idlist_category }}">{{ $category->title }}{{ $category->page ? ' ('.$category->page->Title.')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" id="add-selected-btn" disabled>Add Selected</button>
                    <span><span id="selected-count">0</span> selected</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                        <caption>{{ $combos->total() }} result(s)</caption>
                        <tr>
                            <th><input type="checkbox" class="form-check-input select-all" data-target=".combo-checkbox"></th>
                            <th>Character</th>
                            <th>Inputs</th>
                            <th>Damage</th>
                        </tr>
                        @foreach ($combos as $combo)
                            <tr>
                                <td><input type="checkbox" name="combo_ids[]" value="{{ $combo->idcombo }}" class="form-check-input combo-checkbox"></td>
                                <td>{{ $combo->character->name }}</td>
                                <td style="min-width:400px"><x-combo-link :combo="$combo" /></td>
                                <td>{{ number_format((float) $combo->damage, 0, '', '.') }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>

                {{ $combos->links() }}
            </form>

            <script>
                (function () {
                    const form = document.getElementById('bulk-add-form');
                    const countEl = document.getElementById('selected-count');
                    const addBtn = document.getElementById('add-selected-btn');

                    function updateCount() {
                        const checked = form.querySelectorAll('.combo-checkbox:checked').length;
                        countEl.textContent = checked;
                        addBtn.disabled = checked === 0;
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
                        if (event.target.matches('.combo-checkbox')) {
                            updateCount();
                        }
                    });
                })();
            </script>
        @endif
    </div>
</x-layouts.app>
