<h4 class="mb-2">Most Viewed Combos</h4>
@if ($mostViewedCombos->isEmpty())
    <p>No combos for this game yet.</p>
@else
    <div class="table-responsive">
        <table class="table table-hover align-middle caption-top">
            <caption>Click the character name to see comments if the entry has them.</caption>
            <tr>
                <th>Character</th>
                <th>Inputs</th>
                <th>Damage</th>
                <th>Type</th>
                <th>Submitted</th>
            </tr>
            @foreach ($mostViewedCombos as $combo)
                <tr>
                    <td>
                        @if ($combo->comments || $combo->video)
                            <button class="btn btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMostViewed{{ $combo->idcombo }}">{{ $combo->character->name }}</button>
                        @else
                            {{ $combo->character->name }}
                        @endif
                    </td>
                    <td style="min-width:400px">
                        <x-combo-link :combo="$combo" />
                        @if ($combo->comments || $combo->video)
                            <div class="collapse" id="collapseMostViewed{{ $combo->idcombo }}">
                                {{ $combo->comments }}
                                <x-video-embed :video="$combo->video" />
                            </div>
                        @endif
                    </td>
                    <td>{{ number_format((float) $combo->damage, 0, '', '.') }}</td>
                    <td>{{ $combo->listingType?->title }}</td>
                    <td>{{ $combo->submited?->format('d-m-y') }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endif
<a href="{{ route('games.combos.index', $game) }}" class="link-light">View all combos &rarr;</a>

<h4 class="mb-2 mt-4">Most Viewed Guides</h4>
@if ($mostViewedGuides->isEmpty())
    <p>No guides for this game yet.</p>
@else
    <table class="table table-hover align-middle">
        <tr>
            <th>Title</th>
            <th>Author</th>
            <th>Created</th>
        </tr>
        @foreach ($mostViewedGuides as $guide)
            <tr>
                <td><a href="{{ route('lists.show', $guide) }}" class="text-white">{{ $guide->list_name }}</a></td>
                <td>{{ $guide->user?->nickname ?? 'Anonymous' }}</td>
                <td>{{ $guide->created_at?->format('d-m-y') }}</td>
            </tr>
        @endforeach
    </table>
@endif
<a href="{{ route('lists.search', ['game_idgame' => $game->idgame]) }}" class="link-light">View all guides &rarr;</a>
