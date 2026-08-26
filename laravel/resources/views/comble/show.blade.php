<x-layouts.app
    :title="'Comble'.($isToday ? '' : ' — '.$day->format('M j, Y')).' - Combo好き'"
    description="Guess the game, character and type behind a mystery combo in 5 tries."
>
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <h2>Comble</h2>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <a href="{{ route('comble.show.date', ['date' => $previousDay->toDateString()]) }}" class="btn btn-sm btn-outline-light">&larr; {{ $previousDay->format('M j') }}</a>

            <div class="text-center">
                <div>{{ $isToday ? "Today's puzzle" : $day->format('F j, Y') }}</div>
                <input type="date" class="form-control form-control-sm mt-1" value="{{ $day->toDateString() }}" max="{{ now()->toDateString() }}" onchange="if (this.value) window.location.href = '{{ url('/comble') }}/' + this.value">
            </div>

            @if ($nextDay)
                <a href="{{ route('comble.show.date', ['date' => $nextDay->toDateString()]) }}" class="btn btn-sm btn-outline-light">{{ $nextDay->format('M j') }} &rarr;</a>
            @else
                <span class="btn btn-sm btn-outline-light disabled" style="visibility: hidden;">&rarr;</span>
            @endif
        </div>

        @include('comble._game')
    </div>

    <script type="application/json" id="comble-catalog">{!! json_encode($catalog) !!}</script>
    @vite(['resources/js/comble.js'])
</x-layouts.app>
