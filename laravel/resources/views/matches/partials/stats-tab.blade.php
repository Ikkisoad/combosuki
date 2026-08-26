@if ($characterPickCounts->sum('picks') === 0)
    <p>No matches recorded yet.</p>
@else
    <h5 class="mb-2">Character Picks</h5>
    <p class="text-white-50 small">How many recorded matches each character has been played in, on either side.</p>
    @php $maxPicks = $characterPickCounts->max('picks'); @endphp
    @foreach ($characterPickCounts as $entry)
        <div class="d-flex align-items-center gap-2 mb-1">
            <div style="width: 160px; max-width: 40%;" class="small text-truncate">
                <x-character-icon :character="$entry['character']" size="20" />
                <a href="{{ route('characters.show', [$game, $entry['character']]) }}" class="link-light">{{ $entry['character']->name }}</a>
            </div>
            <div class="flex-grow-1 bg-dark rounded">
                @if ($entry['picks'] === 0)
                    <div class="text-white-50 small px-2" style="height: 18px; line-height: 18px;">No data</div>
                @else
                    <div
                        class="bg-info rounded d-flex align-items-center justify-content-end px-2 text-dark small fw-bold"
                        style="height: 18px; width: {{ $maxPicks > 0 ? max(6, round($entry['picks'] / $maxPicks * 100)) : 0 }}%;"
                    >
                        {{ $entry['picks'] }}
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    <h5 class="mt-4 mb-2">Most Played Matchups</h5>
    @if ($topMatchups->isEmpty())
        <p class="text-white-50">Not enough match data yet.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle combosuki-main-reversed text-white">
                <tr>
                    <th>Matchup</th>
                    <th>Times Played</th>
                </tr>
                @foreach ($topMatchups as $matchup)
                    <tr>
                        <td>
                            @if ($matchup['characterA'])
                                <x-character-icon :character="$matchup['characterA']" size="20" />
                                <a href="{{ route('characters.show', [$game, $matchup['characterA']]) }}" class="link-light">{{ $matchup['characterA']->name }}</a>
                            @endif
                            vs
                            @if ($matchup['characterB'])
                                <x-character-icon :character="$matchup['characterB']" size="20" />
                                <a href="{{ route('characters.show', [$game, $matchup['characterB']]) }}" class="link-light">{{ $matchup['characterB']->name }}</a>
                            @endif
                        </td>
                        <td>{{ $matchup['count'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if ($characterResourceUsage->isNotEmpty())
        <h5 class="mt-4 mb-2">Most Used Resource by Character</h5>
        <p class="text-white-50 small">The resource value most often paired with each character, based on submitted matches.</p>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle combosuki-main-reversed text-white">
                <tr>
                    <th>Character</th>
                    <th>Resource</th>
                    <th>Most Used Value</th>
                    <th>Times</th>
                </tr>
                @foreach ($characterResourceUsage as $entry)
                    <tr>
                        <td>
                            <x-character-icon :character="$entry['character']" size="20" />
                            <a href="{{ route('characters.show', [$game, $entry['character']]) }}" class="link-light">{{ $entry['character']->name }}</a>
                        </td>
                        <td>{{ $entry['resource']->text_name }}</td>
                        <td>
                            <x-resource-value-icon :value="$entry['value']" size="18" />{{ $entry['value']->value }}
                        </td>
                        <td>{{ $entry['uses'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
@endif
