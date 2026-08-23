<h4 class="mb-2">Damage Stats</h4>

@if ($queriesCount === 0)
    <p>This game doesn't have any default queries configured yet.</p>
@elseif (! $topCharacterEntry)
    <p>Not enough combo data yet to compute damage stats.</p>
@else
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card bg-dark text-white h-100">
                <div class="card-body">
                    <h6 class="text-white-50 mb-1">Game Average Damage</h6>
                    <p class="display-6 mb-0">{{ number_format($gameAverageDamage, 0, '', '.') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-dark text-white h-100">
                <div class="card-body">
                    <h6 class="text-white-50 mb-1">Highest Average Damage</h6>
                    <p class="display-6 mb-0">
                        <a href="{{ route('characters.show', [$game, $topCharacterEntry['character']]) }}" class="text-white">{{ $topCharacterEntry['character']->name }}</a>
                    </p>
                    <p class="mb-0">{{ number_format($topCharacterEntry['average'], 0, '', '.') }} avg. damage</p>
                </div>
            </div>
        </div>
    </div>

    <p class="text-white-50 small">Averaged from each character's top result across the game's {{ $queriesCount }} default {{ \Illuminate\Support\Str::plural('query', $queriesCount) }}.</p>

    <h5 class="mt-4 mb-2">Average Damage by Character</h5>
    @php $maxAverage = $characterAverages->max('average'); @endphp
    @foreach ($characterAverages as $entry)
        <div class="d-flex align-items-center gap-2 mb-1">
            <div style="width: 160px; max-width: 40%;" class="small text-truncate">
                <a href="{{ route('characters.show', [$game, $entry['character']]) }}" class="link-light">{{ $entry['character']->name }}</a>
            </div>
            <div class="flex-grow-1 bg-dark rounded">
                <div
                    class="bg-info rounded d-flex align-items-center justify-content-end px-2 text-dark small fw-bold"
                    style="height: 18px; width: {{ $maxAverage > 0 ? max(6, round($entry['average'] / $maxAverage * 100)) : 0 }}%;"
                >
                    {{ number_format($entry['average'], 0, '', '.') }}
                </div>
            </div>
        </div>
    @endforeach
@endif
