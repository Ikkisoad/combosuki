{{-- Rendered both inside challenge/show.blade.php and on its own by
     ChallengeController::dayPanel(), which challenge-day.js fetches to swap
     days in place without a full page reload. --}}
<div data-title="{{ $pageTitle }}" data-date="{{ $day->toDateString() }}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        @if ($previousDay)
            <a href="{{ route('challenge.show.date', ['date' => $previousDay->toDateString()]) }}" class="btn btn-sm btn-outline-light" data-challenge-day="{{ $previousDay->toDateString() }}">&larr; {{ $previousDay->format('M j') }}</a>
        @else
            <span class="btn btn-sm btn-outline-light disabled" style="visibility: hidden;">&larr;</span>
        @endif

        <div class="text-center">
            <div>{{ $isToday ? "Today's challenge" : $day->format('F j, Y') }}</div>
            <input type="date" class="form-control form-control-sm mt-1" id="challenge-date-picker" value="{{ $day->toDateString() }}" @if ($earliestDay) min="{{ $earliestDay->toDateString() }}" @endif max="{{ $today->toDateString() }}">
        </div>

        @if ($nextDay)
            <a href="{{ route('challenge.show.date', ['date' => $nextDay->toDateString()]) }}" class="btn btn-sm btn-outline-light" data-challenge-day="{{ $nextDay->toDateString() }}">{{ $nextDay->format('M j') }} &rarr;</a>
        @else
            <span class="btn btn-sm btn-outline-light disabled" style="visibility: hidden;">&rarr;</span>
        @endif
    </div>

    <div class="card combosuki-main-reversed text-white p-3">
        <x-daily-challenge :challenge="$challenge" />
    </div>
</div>
