@props(['game', 'notation', 'guessesMade', 'finished'])

<span class="comble-reveal">
    {!! $finished
        ? app(\App\Services\ComboNotationRenderer::class)->render($game, $notation)
        : app(\App\Services\CombleRevealer::class)->render($game, $notation, $guessesMade) !!}
</span>
