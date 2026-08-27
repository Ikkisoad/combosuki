<x-layouts.app :title="'Unverified Combos - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($canBulkVerify)
            <form id="bulk-verify-form" method="post" action="{{ route('admin.unverified-combos.bulkVerify', $game) }}">
                @csrf
            </form>
        @endif

        <table class="table table-hover align-middle combosuki-main-reversed text-white">
            <tr>
                <th>
                    @if ($canBulkVerify)
                        <input type="checkbox" class="form-check-input select-all" data-target=".combo-checkbox">
                    @endif
                </th>
                <th>Character</th>
                <th>Combo</th>
                <th>Author</th>
                <th>Submitted</th>
                <th></th>
            </tr>
            @forelse ($combos as $combo)
                <tr>
                    <td>
                        @can('verify', $combo)
                            <input type="checkbox" form="bulk-verify-form" name="combo_ids[]" value="{{ $combo->idcombo }}" class="form-check-input combo-checkbox">
                        @endcan
                    </td>
                    <td>{{ $combo->character->name }}</td>
                    <td><x-combo-link :combo="$combo" /></td>
                    <td>{{ $combo->user?->nickname ?? 'Anonymous' }}</td>
                    <td>{{ $combo->submited?->format('d-m-Y') }}</td>
                    <td>
                        @can('verify', $combo)
                            <form method="post" action="{{ route('combos.verify', $combo) }}"
                                  onsubmit="return confirm('Verify this combo?');" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">Verify</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No unverified combos.</td>
                </tr>
            @endforelse
        </table>

        {{ $combos->links() }}

        @if ($canBulkVerify)
            <div class="sticky-bottom bg-dark p-3 mt-3 border-top border-secondary d-flex align-items-center gap-3">
                <span class="text-white"><span id="selected-count">0</span> selected</span>
                <button type="submit" form="bulk-verify-form" class="btn btn-success" id="verify-selected-btn" disabled>
                    Verify Selected
                </button>
            </div>
        @endif

        <x-admin.edit-nav :game="$game" />
    </div>

    @if ($canBulkVerify)
        <script>
            (function () {
                const bulkForm = document.getElementById('bulk-verify-form');
                const countEl = document.getElementById('selected-count');
                const verifyBtn = document.getElementById('verify-selected-btn');

                function updateCount() {
                    const checked = document.querySelectorAll('.combo-checkbox:checked').length;
                    countEl.textContent = checked;
                    verifyBtn.disabled = checked === 0;
                }

                document.querySelectorAll('.select-all').forEach(function (selectAll) {
                    selectAll.addEventListener('change', function () {
                        document.querySelectorAll(selectAll.dataset.target).forEach(function (checkbox) {
                            checkbox.checked = selectAll.checked;
                        });
                        updateCount();
                    });
                });

                document.querySelectorAll('.combo-checkbox').forEach(function (checkbox) {
                    checkbox.addEventListener('change', updateCount);
                });

                bulkForm.addEventListener('submit', function (event) {
                    const total = document.querySelectorAll('.combo-checkbox:checked').length;

                    if (!confirm(`Verify ${total} selected combo(s)?`)) {
                        event.preventDefault();
                    }
                });
            })();
        </script>
    @endif
</x-layouts.app>
