{{-- Expects $game and $stat (one entry from GameController::damageStatsTab()'s queryStats). --}}
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card bg-dark text-white h-100">
            <div class="card-body">
                <h6 class="text-white-50 mb-1">Average Damage</h6>
                <p class="display-6 mb-0">{{ $stat['average'] !== null ? number_format($stat['average'], 0, '', '.') : '—' }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-dark text-white h-100">
            <div class="card-body">
                <h6 class="text-white-50 mb-1">Highest Damage</h6>
                @if ($stat['topEntry'])
                    <p class="display-6 mb-0">
                        <a href="{{ route('characters.show', [$game, $stat['topEntry']['character']]) }}" class="text-white">{{ $stat['topEntry']['character']->name }}</a>
                    </p>
                    <p class="mb-0">{{ number_format($stat['topEntry']['damage'], 0, '', '.') }} damage</p>
                @else
                    <p class="display-6 mb-0">—</p>
                    <p class="mb-0">Not enough combo data yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<h5 class="mt-4 mb-2">Damage by Character</h5>
@php $maxDamage = $stat['characterDamages']->max('damage'); @endphp
@foreach ($stat['characterDamages'] as $entry)
    <div class="d-flex align-items-center gap-2 mb-1">
        <div style="width: 160px; max-width: 40%;" class="small text-truncate">
            <a href="{{ route('characters.show', [$game, $entry['character']]) }}" class="link-light">{{ $entry['character']->name }}</a>
        </div>
        <div class="flex-grow-1 bg-dark rounded">
            @if ($entry['damage'] === null)
                <div class="text-white-50 small px-2" style="height: 18px; line-height: 18px;">No data</div>
            @else
                <div
                    class="bg-info rounded d-flex align-items-center justify-content-end px-2 text-dark small fw-bold"
                    style="height: 18px; width: {{ $maxDamage > 0 ? max(6, round($entry['damage'] / $maxDamage * 100)) : 0 }}%;"
                >
                    {{ number_format($entry['damage'], 0, '', '.') }}
                </div>
            @endif
        </div>
    </div>
@endforeach
