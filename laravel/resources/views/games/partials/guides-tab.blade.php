<h4 class="mb-2">Featured Guides</h4>
@if ($featuredGuides->isEmpty())
    <p>No featured guides for this game yet.</p>
@else
    <table class="table table-hover align-middle text-white">
        <tr>
            <th>Title</th>
            <th>Author</th>
        </tr>
        @foreach ($featuredGuides as $guide)
            <tr>
                <td><a href="{{ route('lists.show', $guide) }}" class="text-white">{{ $guide->list_name }}</a></td>
                <td>{{ $guide->user?->nickname ?? 'Anonymous' }}</td>
            </tr>
        @endforeach
    </table>
@endif

<h4 class="mb-2 mt-4">Guides</h4>
@if ($guides->isEmpty())
    <p>No guides for this game yet.</p>
@else
    <table class="table table-hover align-middle text-white">
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
