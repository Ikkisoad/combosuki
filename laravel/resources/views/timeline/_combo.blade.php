@props(['combo'])

<div class="card combosuki-main-reversed text-white p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <strong>{{ $combo->user?->nickname ?? 'Anonymous' }}</strong>
        <span class="text-white-50 small">{{ $combo->submited?->format('d-m-y') }}</span>
    </div>

    @if ($combo->comments)
        <p class="mb-2">{!! nl2br(e($combo->comments)) !!}</p>
    @endif

    <x-video-embed :video="$combo->video" />

    <p class="mb-2"><x-combo-link :combo="$combo" /></p>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-secondary" data-share-link="{{ route('combos.show', $combo) }}">Share</button>
        <span class="text-white-50 small">
            <a href="{{ route('games.show', $combo->character->game) }}" class="text-white-50">{{ $combo->character->game->name }}</a>
            &middot; {{ $combo->character->name }}
            &middot; {{ number_format((float) $combo->damage, 0, '', '.') }} dmg
            &middot; {{ $combo->listingType?->title }}
        </span>
    </div>
</div>
