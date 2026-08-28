@props(['value', 'character' => null, 'size' => 20])

@php
    $alias = $value?->aliasFor($character);
    $icon = $alias?->icon ?? $value?->icon;
@endphp

@if ($icon)
    <img src="{{ \Illuminate\Support\Facades\Storage::url($icon) }}" alt="{{ $alias?->alias ?? $value->value }}" style="height: {{ $size }}px; width: {{ $size }}px; object-fit: contain;" class="me-1">
@endif
