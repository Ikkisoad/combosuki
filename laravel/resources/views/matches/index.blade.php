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

        <form method="get" action="{{ route('games.matches.index', $game) }}" class="card combosuki-main-reversed text-white p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label">Character</label>
                    <select name="character_a" class="form-select">
                        <option value="-">Any Character</option>
                        @foreach ($characters as $character)
                            <option value="{{ $character->idcharacter }}" @selected(request('character_a') == $character->idcharacter)>{{ $character->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label">vs Character</label>
                    <select name="character_b" class="form-select">
                        <option value="-">Any</option>
                        @foreach ($characters as $character)
                            <option value="{{ $character->idcharacter }}" @selected(request('character_b') == $character->idcharacter)>{{ $character->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label">Player</label>
                    <input type="text" name="player" class="form-control" value="{{ request('player') }}" placeholder="Name or tag">
                </div>
                <div class="col-auto">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-auto">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-auto">
                    <label class="form-label">Video</label>
                    <input type="text" name="video" class="form-control" value="{{ request('video') }}" placeholder="URL contains&hellip;">
                </div>
                @foreach ($matchResources as $resource)
                    <div class="col-auto">
                        <label class="form-label">{{ $resource->text_name }}</label>
                        <select name="resource_{{ $resource->idgame_resources }}_a" class="form-select">
                            <option value="-">Any {{ $resource->text_name }}</option>
                            @foreach ($resource->values as $value)
                                <option value="{{ $value->idResources_values }}" @selected(request('resource_'.$resource->idgame_resources.'_a') == $value->idResources_values)>{{ $value->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label">vs {{ $resource->text_name }}</label>
                        <select name="resource_{{ $resource->idgame_resources }}_b" class="form-select">
                            <option value="-">Any</option>
                            @foreach ($resource->values as $value)
                                <option value="{{ $value->idResources_values }}" @selected(request('resource_'.$resource->idgame_resources.'_b') == $value->idResources_values)>{{ $value->value }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            @php
                $filterFields = array_merge(
                    ['character_a', 'character_b', 'player', 'date_from', 'date_to', 'video'],
                    $matchResources->flatMap(fn ($resource) => ["resource_{$resource->idgame_resources}_a", "resource_{$resource->idgame_resources}_b"])->all()
                );
            @endphp

            <div class="mt-3">
                <button type="submit" class="btn btn-info">Filter</button>
                @if (request()->anyFilled($filterFields))
                    <a href="{{ route('games.matches.index', $game) }}" class="btn btn-outline-light">Clear</a>
                @endif
            </div>
        </form>

        @if ($matches->total() === 0 && request()->anyFilled($filterFields))
            <div class="alert alert-warning">No matches found for these filters.</div>
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
                            <x-character-icon :character="$match->playerOneCharacter" />
                            @if ($match->playerOneUser)
                                <a href="{{ route('users.show', $match->playerOneUser) }}">{{ $match->player_one }}</a>
                            @else
                                {{ $match->player_one }}
                            @endif
                            ({{ $match->playerOneCharacter->name }}@foreach ($match->resources->where('player', 1) as $matchResource), {{ $matchResource->resourceValue->value }}@endforeach)
                        </td>
                        <td>
                            <x-character-icon :character="$match->playerTwoCharacter" />
                            @if ($match->playerTwoUser)
                                <a href="{{ route('users.show', $match->playerTwoUser) }}">{{ $match->player_two }}</a>
                            @else
                                {{ $match->player_two }}
                            @endif
                            ({{ $match->playerTwoCharacter->name }}@foreach ($match->resources->where('player', 2) as $matchResource), {{ $matchResource->resourceValue->value }}@endforeach)
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
