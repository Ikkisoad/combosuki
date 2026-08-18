@props(['game', 'notation'])

@php
    $tokens = app(\App\Services\ComboNotationRenderer::class)->tokenize($game, $notation);
@endphp

<span class="combo-notation">
    @foreach ($tokens as $token)
        @if ($token['type'] === 'button')
            <img class="img-fluid" style="display:inline; height:2em;" alt="" src="{{ asset('img/buttons/'.$token['value'].'.png') }}">
        @else
            {{ $token['value'] }}
        @endif
    @endforeach
</span>
