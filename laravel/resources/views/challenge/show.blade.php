<x-layouts.app
    :title="'Challenge'.($isToday ? '' : ' — '.$day->format('M j, Y')).' - Combo好き'"
    description="Browse the daily combo challenge for today or any past day."
>
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <h2>Challenge</h2>

        <div class="d-flex justify-content-between align-items-center mb-2">
            @if ($previousDay)
                <a href="{{ route('challenge.show.date', ['date' => $previousDay->toDateString()]) }}" class="btn btn-sm btn-outline-light">&larr; {{ $previousDay->format('M j') }}</a>
            @else
                <span class="btn btn-sm btn-outline-light disabled" style="visibility: hidden;">&larr;</span>
            @endif

            <div class="text-center">
                <div>{{ $isToday ? "Today's challenge" : $day->format('F j, Y') }}</div>
                <input type="date" class="form-control form-control-sm mt-1" value="{{ $day->toDateString() }}" @if ($earliestDay) min="{{ $earliestDay->toDateString() }}" @endif max="{{ now()->toDateString() }}" onchange="if (this.value) window.location.href = '{{ url('/challenge') }}/' + this.value">
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

        <ul class="nav nav-tabs mt-3" id="challenge-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="ranking-tab" data-bs-toggle="tab" data-bs-target="#ranking-pane" type="button" role="tab" aria-controls="ranking-pane" aria-selected="true">Leaderboard</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar-pane" type="button" role="tab" aria-controls="calendar-pane" aria-selected="false">Calendar</button>
            </li>
        </ul>

        <div class="tab-content combosuki-main-reversed text-white p-3 border border-top-0" id="challenge-tabs-content">
            <div class="tab-pane fade show active" id="ranking-pane" role="tabpanel" aria-labelledby="ranking-tab">
                <div id="ranking-results" data-endpoint="{{ route('challenge.tabs.ranking') }}"></div>
            </div>

            <div class="tab-pane fade" id="calendar-pane" role="tabpanel" aria-labelledby="calendar-tab">
                <div id="challenge-calendar" data-endpoint="{{ route('challenge.tabs.calendar') }}" data-day-url-base="{{ url('/challenge') }}"></div>
            </div>
        </div>
    </div>

    @vite(['resources/js/challenge-calendar.js'])

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var rankingTabButton = document.getElementById('ranking-tab');
            var rankingResults = document.getElementById('ranking-results');

            function loadRanking() {
                if (rankingResults.dataset.loaded === '1') {
                    return;
                }
                rankingResults.innerHTML = '<p class="text-white-50">Loading&hellip;</p>';
                fetch(rankingResults.dataset.endpoint)
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        rankingResults.innerHTML = html;
                        rankingResults.dataset.loaded = '1';
                    })
                    .catch(function () {
                        rankingResults.innerHTML = '<p class="text-danger">Failed to load the leaderboard.</p>';
                    });
            }

            rankingTabButton.addEventListener('shown.bs.tab', loadRanking);

            // The leaderboard tab is active by default, so its data doesn't
            // wait for a shown.bs.tab event that already fired before this
            // listener was attached.
            loadRanking();
        });
    </script>
</x-layouts.app>
