@props(['character', 'resourceValue' => null, 'title' => null])

@php
    $resourceValueAlias = $resourceValue?->aliasFor($character);
    $resourceValueIcon = $resourceValueAlias?->icon ?? $resourceValue?->icon;
@endphp

<div class="character-card" @if ($title) title="{{ $title }}" @endif>
    <div class="character-icon-wrap">
        @if ($character->image)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($character->image) }}" alt="{{ $character->name }}" class="character-icon">
        @endif
        @if ($resourceValueIcon)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($resourceValueIcon) }}" alt="{{ $resourceValueAlias?->alias ?? $resourceValue->value }}" class="resource-badge">
        @endif
    </div>
    <div class="small text-center">{{ $character->name }}</div>
</div>
