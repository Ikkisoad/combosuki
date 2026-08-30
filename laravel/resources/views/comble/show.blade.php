<x-layouts.app
    :title="'Comble'.($isToday ? '' : ' — '.$day->format('M j, Y')).' - Combo好き'"
    description="Guess the game, character and type behind a mystery combo in 5 tries."
>
    <x-slot:styles>
        {{--
            Discord's own client is the only thing that can ever frame this
            page (see SecurityHeaders) — resources/js/comble.js's
            bootDiscordActivity() reads this to detect being framed and
            know which Discord Application to hand off to.
        --}}
        <meta name="discord-application-id" content="{{ config('services.discord.application_id') }}">
    </x-slot:styles>

    {{--
        No jumbotron/nav-bar here — Comble lives on its own comble.*
        subdomain (routes/comble.php) and is opened as its own tab from the
        main site's nav bar rather than navigated to in place, so there's
        no "back to the main site" link to provide on this page.
    --}}
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
                {{--
                    route()-generated with a placeholder date, not a
                    hardcoded "/comble/" prefix: comble.show.date's actual
                    path differs between the domain-scoped subdomain ("/{date}")
                    and the local-dev prefix fallback ("/comble/{date}") — see
                    routes/comble.php.
                --}}
                <input type="date" class="form-control form-control-sm mt-1" value="{{ $day->toDateString() }}" max="{{ now()->toDateString() }}" data-date-url-template="{{ route('comble.show.date', ['date' => 'DATE_PLACEHOLDER']) }}" onchange="if (this.value) window.location.href = this.dataset.dateUrlTemplate.replace('DATE_PLACEHOLDER', this.value)">
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
    {{--
        route()-generated, not hardcoded in the JS: these routes live under
        an /activity sub-path on the dedicated comble.* subdomain in
        production but under an "/activity/comble" prefix on the main
        domain in local dev — see routes/activity.php's docblock.
    --}}
    <script type="application/json" id="activity-comble-urls">{!! json_encode([
        'token' => route('activity.comble.token'),
        'state' => route('activity.comble.state'),
    ]) !!}</script>
    @vite(['resources/js/comble.js'])
</x-layouts.app>
