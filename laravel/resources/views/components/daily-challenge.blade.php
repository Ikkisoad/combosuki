@props(['challenge'])

@php
    $query = $challenge['query'];
    $character = $challenge['character'];
    $combo = $challenge['combo'];
    $criteria = $challenge['criteria'] ?? [];
@endphp

@if (! $query || ! $character)
    <p>No challenge is available yet &mdash; check back once some default queries are configured.</p>
@else
    <h3 class="mt-0">{{ $character->game->name }} &mdash; {{ $character->name }} &mdash; {{ $query->label }}</h3>

    <p class="mb-1">A qualifying combo must satisfy:</p>
    <ul class="mb-2">
        <li>Character: {{ $character->name }}</li>
        @foreach ($criteria as $criterion)
            <li>{{ $criterion }}</li>
        @endforeach
    </ul>

    @if ($combo)
        <table class="table table-hover align-middle caption-top text-white">
            <tr>
                <th>Character</th>
                <th>Inputs</th>
                <th>Damage</th>
            </tr>
            <tr>
                <td>
                    @if ($combo->comments || $combo->video)
                        <button class="btn btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-challenge-{{ $combo->idcombo }}">{{ $combo->character->name }}</button>
                    @else
                        {{ $combo->character->name }}
                    @endif
                </td>
                <td style="min-width:400px">
                    <x-combo-link :combo="$combo" />
                    @if ($combo->comments || $combo->video)
                        <div class="collapse" id="collapse-challenge-{{ $combo->idcombo }}">
                            {{ $combo->comments }}
                            <x-video-embed :video="$combo->video" />
                        </div>
                    @endif
                </td>
                <td>{{ number_format((float) $combo->damage, 0, '', '.') }}</td>
            </tr>
        </table>
        <p>Think you can do better? <a href="{{ route('games.combos.create', $character->game) }}?query={{ $query->idquery }}&characterid={{ $character->idcharacter }}" class="link-light">Go beat it &rarr;</a></p>
    @else
        <p>No combo found for this challenge yet &mdash; <a href="{{ route('games.combos.create', $character->game) }}?query={{ $query->idquery }}&characterid={{ $character->idcharacter }}" class="link-light">be the first to submit one!</a></p>
    @endif
@endif
