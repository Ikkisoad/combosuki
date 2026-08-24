<x-layouts.app :title="'Moderated Games - '.$user->nickname">
    <x-nav-bar />

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

        <h1 class="text-white">Games Moderated by &ldquo;{{ $user->nickname }}&rdquo;</h1>
        <p class="text-white">
            <a href="{{ route('admin.users.index') }}" class="link-light">&larr; Back to Manage Users</a>
        </p>

        <form method="post" action="{{ route('admin.users.moderated-games.update', $user) }}" id="moderated-games-form">
            @csrf

            <div class="d-flex gap-2 align-items-end mb-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <span><span id="selected-count">{{ count($moderatedGameIds) }}</span> selected</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle combosuki-main-reversed text-white">
                    <tr>
                        <th><input type="checkbox" class="form-check-input select-all" data-target=".game-checkbox"></th>
                        <th>Game</th>
                    </tr>
                    @foreach ($games as $game)
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    name="game_ids[]"
                                    value="{{ $game->idgame }}"
                                    class="form-check-input game-checkbox"
                                    @checked(in_array($game->idgame, $moderatedGameIds, true))
                                >
                            </td>
                            <td>{{ $game->name }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>

            <button type="submit" class="btn btn-primary">Save</button>
        </form>

        <script>
            (function () {
                const form = document.getElementById('moderated-games-form');
                const countEl = document.getElementById('selected-count');

                function updateCount() {
                    countEl.textContent = form.querySelectorAll('.game-checkbox:checked').length;
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
                    if (event.target.matches('.game-checkbox')) {
                        updateCount();
                    }
                });
            })();
        </script>
    </div>
</x-layouts.app>
