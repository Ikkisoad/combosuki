<x-layouts.app :title="'Matches - '.$game->name">
    <x-jumbotron :height="100" />
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="mb-0">Matches</h2>
            @auth
                <a href="{{ route('games.matches.create', $game) }}" class="btn btn-combosuki text-white">Submit a match</a>
            @endauth
        </div>

        @if ($game->matches_url)
            <div class="alert alert-info">
                This game's matches are also tracked on an external database:
                <a href="{{ $game->matches_url }}" target="_blank" class="alert-link">{{ $game->matches_url }}</a>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                <caption>{{ $matches->total() }} match(es)</caption>
                <tr>
                    <th>Player 1</th>
                    <th>Player 2</th>
                    <th>Video</th>
                    <th>Played</th>
                    @auth
                        <th></th>
                    @endauth
                </tr>
                @foreach ($matches as $match)
                    <tr>
                        <td>
                            @if ($match->playerOneUser)
                                <a href="{{ route('users.show', $match->playerOneUser) }}">{{ $match->player_one }}</a>
                            @else
                                {{ $match->player_one }}
                            @endif
                            ({{ $match->playerOneCharacter->name }})
                        </td>
                        <td>
                            @if ($match->playerTwoUser)
                                <a href="{{ route('users.show', $match->playerTwoUser) }}">{{ $match->player_two }}</a>
                            @else
                                {{ $match->player_two }}
                            @endif
                            ({{ $match->playerTwoCharacter->name }})
                        </td>
                        <td style="min-width:300px">
                            <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#video-{{ $match->idmatch }}" aria-expanded="false" aria-controls="video-{{ $match->idmatch }}" aria-label="Show video">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 5l5 6 5-6" />
                                </svg>
                            </button>
                            <div class="collapse mt-2" id="video-{{ $match->idmatch }}">
                                <x-video-embed :video="$match->video" />
                            </div>
                        </td>
                        <td>{{ $match->played_at->format('d-m-y') }}</td>
                        @auth
                            <td>
                                @can('update', $match)
                                    <a href="{{ route('matches.edit', $match) }}" class="btn btn-secondary btn-sm">Edit</a>
                                @endcan
                            </td>
                        @endauth
                    </tr>
                @endforeach
            </table>
        </div>

        {{ $matches->links() }}
    </div>
</x-layouts.app>
