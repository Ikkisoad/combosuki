@props(['game', 'notation'])

@php
    $tokens = app(\App\Services\ComboNotationRenderer::class)->tokenize($game, $notation);
@endphp

<span class="combo-notation">
    @foreach ($tokens as $token)
        @if ($token['type'] === 'colored')
            <span style="color: {{ $token['color'] }};">{{ $token['value'] }}</span>
        @else
            {{ $token['value'] }}
        @endif
    @endforeach
</span>
