@if ($guides->isEmpty())
    <p>No guides for this game yet.</p>
@else
    <table class="table table-hover align-middle">
        <tr>
            <th>Title</th>
            <th>Author</th>
        </tr>
        @foreach ($guides as $guide)
            <tr>
                <td><a href="{{ route('lists.show', $guide) }}" class="text-white">{{ $guide->list_name }}</a></td>
                <td>{{ $guide->user?->nickname ?? 'Anonymous' }}</td>
            </tr>
        @endforeach
    </table>
@endif
<a href="{{ route('lists.search', ['game_idgame' => $game->idgame]) }}" class="link-light">View all guides &rarr;</a>
