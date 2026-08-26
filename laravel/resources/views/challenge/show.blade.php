<x-layouts.app
    :title="'Challenge'.($isToday ? '' : ' — '.$day->format('M j, Y')).' - Combo好き'"
    description="Browse the daily combo challenge for today or any past day."
>
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <h2>Challenge</h2>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <a href="{{ route('challenge.show.date', ['date' => $previousDay->toDateString()]) }}" class="btn btn-sm btn-outline-light">&larr; {{ $previousDay->format('M j') }}</a>

            <div class="text-center">
                <div>{{ $isToday ? "Today's challenge" : $day->format('F j, Y') }}</div>
                <input type="date" class="form-control form-control-sm mt-1" value="{{ $day->toDateString() }}" max="{{ now()->toDateString() }}" onchange="if (this.value) window.location.href = '{{ url('/challenge') }}/' + this.value">
            </div>

            @if ($nextDay)
                <a href="{{ route('challenge.show.date', ['date' => $nextDay->toDateString()]) }}" class="btn btn-sm btn-outline-light">{{ $nextDay->format('M j') }} &rarr;</a>
            @else
                <span class="btn btn-sm btn-outline-light disabled" style="visibility: hidden;">&rarr;</span>
            @endif
        </div>

        <div class="card combosuki-main-reversed text-white p-3">
            <x-daily-challenge :challenge="$challenge" />
        </div>
    </div>
</x-layouts.app>
