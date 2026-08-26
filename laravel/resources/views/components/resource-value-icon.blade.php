@props(['value', 'size' => 20])

@if ($value?->icon)
    <img src="{{ \Illuminate\Support\Facades\Storage::url($value->icon) }}" alt="{{ $value->value }}" style="height: {{ $size }}px; width: {{ $size }}px; object-fit: contain;" class="me-1">
@endif
