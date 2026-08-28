<x-layouts.app :title="($game ? $game->name.' Tier Lists' : 'Tier Lists').' - Combo好き'" description="Browse tier lists submitted by the community.">
    <x-jumbotron :height="150" />
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>{{ $game ? $game->name.' Tier Lists' : 'Tier Lists' }}</h2>
            <a href="{{ route('tier-lists.create') }}" class="btn btn-primary">Create Tier List</a>
        </div>

        @if ($game)
            <p><a href="{{ route('tier-lists.index') }}" class="link-light">&larr; All games</a></p>
        @endif

        <form method="get" action="{{ route('tier-lists.index') }}" class="card combosuki-main-reversed text-white p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label">Game</label>
                    <select name="game_idgame" class="form-select" onchange="this.form.submit()">
                        <option value="">Any Game</option>
                        @foreach ($games as $gameOption)
                            <option value="{{ $gameOption->idgame }}" @selected(request('game_idgame') == $gameOption->idgame)>{{ $gameOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label">Author</label>
                    <input type="text" name="author" class="form-control" value="{{ request('author') }}" placeholder="Nickname contains&hellip;">
                </div>
                <div class="col-auto">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-auto">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                @if ($game)
                    <div class="col-auto">
                        <label class="form-label">Patch</label>
                        <select name="tier_patch" class="form-select">
                            <option value="all">Any</option>
                            @foreach ($game->patches as $patch)
                                <option value="{{ $patch->idgame_patch }}" @selected(request('tier_patch') == $patch->idgame_patch)>{{ $patch->label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            @php
                $filterFields = ['game_idgame', 'author', 'date_from', 'date_to', 'tier_patch'];
            @endphp

            <div class="mt-3">
                <button type="submit" class="btn btn-info">Filter</button>
                @if (request()->anyFilled($filterFields))
                    <a href="{{ route('tier-lists.index') }}" class="btn btn-outline-light">Clear</a>
                @endif
            </div>
        </form>

        @if ($tierLists->isEmpty())
            <p>{{ request()->anyFilled($filterFields) ? 'No tier lists found for these filters.' : 'No tier lists yet.' }}</p>
        @else
            <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                <tr>
                    <th>Title</th>
                    <th>Game</th>
                    <th>Author</th>
                    <th>Date</th>
                </tr>
                @foreach ($tierLists as $tierList)
                    <tr>
                        <td><a href="{{ route('tier-lists.show', $tierList) }}" class="text-white">{{ $tierList->title }}</a></td>
                        <td>{{ $tierList->game->name }}</td>
                        <td>{{ $tierList->user?->nickname ?? 'Anonymous' }}</td>
                        <td>{{ $tierList->created_at->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </table>

            {{ $tierLists->links('pagination::bootstrap-5') }}
        @endif
    </div>
</x-layouts.app>
