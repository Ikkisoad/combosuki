@props([
    'title' => 'Combo好き',
    'description' => 'Community-fueled searchable environment that shares and perfects combos.',
    'image' => 'https://combosuki.com/img/combosuki.png',
    'player' => null,
])

@php
    $bgColor = request()->cookie('color', '920000');
    $bgColor = preg_match('/^[0-9a-fA-F]{6}$/', $bgColor) ? $bgColor : '920000';
@endphp
<!doctype html>
<html lang="en" style="--combosuki-bg-color: #{{ $bgColor }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }}</title>

    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:type" content="website" />
    <meta property="og:image" content="{{ $image ?? 'https://combosuki.com/img/combosuki.png' }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:description" content="{{ $description }}" />
    <meta name="theme-color" content="#C62114" />
    <meta name="description" content="{{ $description }}">

    @if ($player)
        <meta property="og:video" content="{{ $player['player'] }}" />
        <meta property="og:video:secure_url" content="{{ $player['player'] }}" />
        <meta property="og:video:type" content="{{ $player['kind'] === 'video' ? 'video/mp4' : 'text/html' }}" />
        <meta property="og:video:width" content="{{ $player['width'] }}" />
        <meta property="og:video:height" content="{{ $player['height'] }}" />
    @endif

    @if ($player && $player['kind'] === 'html')
        <meta name="twitter:card" content="player" />
        <meta name="twitter:player" content="{{ $player['player'] }}" />
        <meta name="twitter:player:width" content="{{ $player['width'] }}" />
        <meta name="twitter:player:height" content="{{ $player['height'] }}" />
    @else
        <meta name="twitter:card" content="summary_large_image" />
    @endif
    <meta name="twitter:title" content="{{ $title }}" />
    <meta name="twitter:description" content="{{ $description }}" />
    <meta name="twitter:image" content="{{ $image ?? 'https://combosuki.com/img/combosuki.png' }}" />

    {{--
        Root-relative paths, not asset() (which defaults to an absolute URL
        built from the current request's host) — this layout also renders
        on comble.show, which can be viewed through Discord's Activity
        proxy, where the page is displayed from a different origin than
        our server sees in the request. A root-relative path resolves
        against whatever origin is actually serving the page in every
        context; an absolute URL with our own host baked in gets blocked as
        a direct external fetch by Discord's sandboxed iframe — see
        AppServiceProvider's matching fix for @vite() output.
    --}}
    <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/img/favicon-16x16.png">

    <link rel="preload" as="image" href="/img/combosuki.webp" fetchpriority="high">
    <link rel="preload" as="image" href="/img/bg/bolinhas2.webp">
    <link rel="preload" as="image" href="/img/bg/risco2.webp">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{ $styles ?? '' }}
</head>
<body>
    {{ $slot }}

    <div class="modal fade" id="global-confirm-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content combosuki-main-reversed text-white">
                <div class="modal-header">
                    <h5 class="modal-title">Please Confirm</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="global-confirm-modal-message" class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="global-confirm-modal-accept" class="btn btn-danger">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
