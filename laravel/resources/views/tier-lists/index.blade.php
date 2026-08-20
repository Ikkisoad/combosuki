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

        @if ($tierLists->isEmpty())
            <p>No tier lists yet.</p>
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
