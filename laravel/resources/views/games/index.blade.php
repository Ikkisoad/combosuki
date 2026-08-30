<x-layouts.app :title="'Games - Combo好き'">
    <x-jumbotron :height="100" />
    <x-nav-bar />

    <div class="container my-3">
        <h2>Games</h2>

        <form method="get" action="{{ route('games.index') }}" class="row g-2 align-items-end mb-3">
            <div class="col-auto">
                <label for="name" class="form-label">Search</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ $search }}" placeholder="Game name">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
                @if ($search !== '')
                    <a href="{{ route('games.index') }}" class="btn btn-secondary">Reset</a>
                @endif
            </div>
        </form>

        <div class="row">
            @forelse ($games as $game)
                <x-game-card :game="$game" />
            @empty
                <p>No games found.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
