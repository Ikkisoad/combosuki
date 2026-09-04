@props(['character', 'size' => 32])

@if ($character->image)
    <img src="{{ \Illuminate\Support\Facades\Storage::url($character->image) }}" alt="{{ $character->name }}" width="{{ $size }}" height="{{ $size }}" loading="lazy" style="height: {{ $size }}px; width: {{ $size }}px; object-fit: cover; border-radius: 4px;" class="me-1">
@endif
