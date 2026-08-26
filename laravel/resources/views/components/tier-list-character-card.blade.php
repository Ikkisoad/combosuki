@props(['character', 'resourceValue' => null, 'title' => null])

<div class="character-card" @if ($title) title="{{ $title }}" @endif>
    <div class="character-icon-wrap">
        @if ($character->image)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($character->image) }}" alt="{{ $character->name }}" class="character-icon">
        @endif
        @if ($resourceValue?->icon)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($resourceValue->icon) }}" alt="{{ $resourceValue->value }}" class="resource-badge">
        @endif
    </div>
    <div class="small text-center">{{ $character->name }}</div>
</div>
