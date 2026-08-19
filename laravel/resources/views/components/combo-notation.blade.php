@props(['game', 'notation'])

<span class="combo-notation">{!! app(\App\Services\ComboNotationRenderer::class)->render($game, $notation) !!}</span>
