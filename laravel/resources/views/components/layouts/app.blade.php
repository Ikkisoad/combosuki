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

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('img/favicon-16x16.png') }}">

    <link rel="preload" as="image" href="{{ asset('img/combosuki.webp') }}" fetchpriority="high">
    <link rel="preload" as="image" href="{{ asset('img/bg/bolinhas2.webp') }}">
    <link rel="preload" as="image" href="{{ asset('img/bg/risco2.webp') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{ $styles ?? '' }}
</head>
<body>
    {{ $slot }}
</body>
</html>
