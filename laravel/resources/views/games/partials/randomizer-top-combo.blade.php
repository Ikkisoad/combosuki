@if ($combo)
    <p class="mb-1"><strong>Top combo for this roll:</strong></p>
    <x-combo-link :combo="$combo" />
    <span class="text-white-50">&mdash; {{ number_format((float) $combo->damage, 0, '', '.') }} damage</span>
@else
    <p class="text-white-50 mb-0">No combo submitted for this roll yet &mdash; be the first!</p>
@endif
