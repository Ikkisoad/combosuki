<!doctype html>
<html lang="en" style="--combosuki-bg-color: #920000">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
    <meta name="discord-application-id" content="{{ $applicationId }}">

    <title>Comble</title>

    {{--
        Not x-layouts.app: that component calls csrf_token(), which needs a
        Laravel session that these routes deliberately never start (see
        routes/activity.php) — it would throw here instead of rendering.
    --}}
    @vite(['resources/css/app.css', 'resources/js/activity-comble.js'])
</head>
<body class="combosuki-main-reversed text-white">
    <div class="container-fluid p-2" id="activity-comble-root">
        <h5 class="mb-2">Comble</h5>

        <div id="activity-comble-status" class="text-white-50">Connecting to Discord&hellip;</div>
        <div id="activity-comble-game" style="display: none;"></div>
    </div>

    <script type="application/json" id="comble-catalog">{!! json_encode($catalog) !!}</script>
    {{--
        route()-generated, not hardcoded in the JS: these routes live at a
        dedicated subdomain's root in production but under an
        "/activity/comble" prefix on the main domain in local dev (see
        routes/activity.php's docblock) — the JS shouldn't need to know which.
    --}}
    <script type="application/json" id="activity-comble-urls">{!! json_encode([
        'token' => route('activity.comble.token'),
        'state' => route('activity.comble.state'),
        'guess' => route('activity.comble.guess'),
    ]) !!}</script>
</body>
</html>
