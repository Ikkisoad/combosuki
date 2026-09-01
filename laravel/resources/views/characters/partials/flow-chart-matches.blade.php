@if ($combos->isEmpty())
    <p class="text-white-50 small mb-0">
        No existing combo matches this path yet &mdash; that's fine, keep exploring, or
        <a href="{{ route('games.combos.create', $game) }}?characterid={{ $character->idcharacter }}" class="link-light">submit one</a>.
    </p>
@else
    <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white mb-0">
        <tr>
            <th></th>
            <th>Inputs</th>
            <th>Damage</th>
        </tr>
        @foreach ($combos as $combo)
            <tr>
                <td>
                    @if ($combo->comments || $combo->video)
                        <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-match-{{ $combo->idcombo }}">Details</button>
                    @endif
                </td>
                <td style="min-width:400px">
                    <x-combo-link :combo="$combo" />
                    @if ($combo->comments || $combo->video)
                        <div class="collapse" id="collapse-match-{{ $combo->idcombo }}">
                            {{ $combo->comments }}
                            <x-video-embed :video="$combo->video" />
                        </div>
                    @endif
                </td>
                <td>{{ number_format((float) $combo->damage, 0, '', '.') }}</td>
            </tr>
        @endforeach
    </table>
@endif
