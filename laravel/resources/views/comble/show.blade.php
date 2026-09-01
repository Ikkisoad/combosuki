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
        TEMPORARY diagnostic — remove once the Discord-proxy asset-loading
        issue is understood. Inline and synchronous (no dependency on the
        Vite bundle, which is exactly what's suspected of failing to load),
        so it renders regardless of whether comble.js itself loads. Shows
        what URL the browser believes this page is actually at once Discord
        has loaded it — if root-relative asset URLs are resolving against
        the wrong base (e.g. missing a /.proxy/ path segment Discord's
        client may be using), this reveals it directly, without needing
        Discord's own DevTools.
    --}}
    <div id="debug-location-info" style="background:#000;color:#0f0;padding:8px;font-family:monospace;font-size:11px;word-break:break-all;"></div>
    <div id="debug-fetch-info" style="background:#000;color:#ff0;padding:8px;font-family:monospace;font-size:11px;word-break:break-all;">fetch tests pending…</div>
    <script>
        document.getElementById('debug-location-info').textContent =
            'href=' + window.location.href
            + ' | base=' + document.baseURI
            + ' | framed=' + (window.self !== window.top);

        // Runs after the document (including the built stylesheet/script
        // tags Vite injects further down) has parsed, and independently
        // re-fetches the exact same URLs the browser already tried for the
        // stylesheet and the comble.js module script — reporting the
        // actual result (success, status code, or network/CORS failure)
        // directly on the page, since that's otherwise only visible in
        // DevTools.
        document.addEventListener('DOMContentLoaded', function () {
            // The shared layout's own Vite call for the site-wide app CSS/JS
            // also renders a stylesheet link and a module script in <head>,
            // ahead of this view's own Vite tag for comble.js further down
            // — so "the first match" would silently grab the wrong ones.
            // Filtering by filename picks the actual comble.js/app.css
            // tags specifically.
            const cssLink = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).find((el) => el.href.includes('app-'));
            const jsScript = Array.from(document.querySelectorAll('script[type="module"]')).find((el) => el.src.includes('comble-'));
            const targets = [
                ['css', cssLink ? cssLink.href : null],
                ['js', jsScript ? jsScript.src : null],
            ];

            Promise.all(targets.map(function ([label, url]) {
                if (! url) {
                    return Promise.resolve(label + '=NO_TAG_FOUND');
                }

                return fetch(url, { cache: 'no-store' })
                    .then(function (res) {
                        return label + '=' + res.status + ' (' + url + ')';
                    })
                    .catch(function (err) {
                        return label + '=FETCH_ERROR:' + err.message + ' (' + url + ')';
                    });
            })).then(function (parts) {
                document.getElementById('debug-fetch-info').textContent = parts.join(' | ');
            });
        });
    </script>

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
            {{--
                absolute: false on every route() call below that the
                browser will actually request/navigate to (not the
                MainSiteUrl:: calls elsewhere, which are intentionally
                cross-domain) — route() defaults to an absolute URL built
                from the current request's host, which gets blocked as a
                direct external fetch by Discord's sandboxed iframe when
                this page is viewed through its Activity proxy (same root
                cause as the earlier @vite()/asset() fix in
                AppServiceProvider — this is the route()-helper half of it).
            --}}
            <a href="{{ route('comble.show.date', ['date' => $previousDay->toDateString()], absolute: false) }}" class="btn btn-sm btn-outline-light">&larr; {{ $previousDay->format('M j') }}</a>

            <div class="text-center">
                <div>{{ $isToday ? "Today's puzzle" : $day->format('F j, Y') }}</div>
                {{--
                    route()-generated with a placeholder date, not a
                    hardcoded "/comble/" prefix: comble.show.date's actual
                    path differs between the domain-scoped subdomain ("/{date}")
                    and the local-dev prefix fallback ("/comble/{date}") — see
                    routes/comble.php.
                --}}
                <input type="date" class="form-control form-control-sm mt-1" value="{{ $day->toDateString() }}" max="{{ now()->toDateString() }}" data-date-url-template="{{ route('comble.show.date', ['date' => 'DATE_PLACEHOLDER'], absolute: false) }}" onchange="if (this.value) window.location.href = this.dataset.dateUrlTemplate.replace('DATE_PLACEHOLDER', this.value)">
            </div>

            @if ($nextDay)
                <a href="{{ route('comble.show.date', ['date' => $nextDay->toDateString()], absolute: false) }}" class="btn btn-sm btn-outline-light">{{ $nextDay->format('M j') }} &rarr;</a>
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
        'token' => route('activity.comble.token', [], absolute: false),
        'state' => route('activity.comble.state', [], absolute: false),
    ]) !!}</script>
    @vite(['resources/js/comble.js'])
</x-layouts.app>
