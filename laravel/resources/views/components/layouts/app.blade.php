@props([
    'title' => 'Combo好き',
    'description' => 'Community-fueled searchable environment that shares and perfects combos.',
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

    <title>{{ $title }}</title>

    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:type" content="website" />
    <meta property="og:image" content="https://combosuki.com/img/combosuki.png" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:description" content="{{ $description }}" />
    <meta name="theme-color" content="#C62114" />
    <meta name="description" content="{{ $description }}">

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('img/favicon-16x16.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{ $styles ?? '' }}
</head>
<body>
    {{ $slot }}
</body>
</html>
