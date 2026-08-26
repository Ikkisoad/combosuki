@foreach ($grouped as $categoryId => $combos)
    <h2 class="mt-3">{{ $categoryId == 0 ? 'No Category' : $categories->get($categoryId)?->title }}</h2>
    @if ($categoryId != 0 && $categories->get($categoryId)?->description)
        <p class="text-white-50">{{ $categories->get($categoryId)->description }}</p>
    @endif
    <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
        <tr>
            <th>Character</th>
            <th>Inputs</th>
            <th>Damage</th>
            <th>Type</th>
        </tr>
        @foreach ($combos as $combo)
            <tr>
                <td>
                    @if ($combo->comments || $combo->video)
                        <button class="btn btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $combo->idcombo }}">{{ $combo->character->name }}</button>
                    @else
                        {{ $combo->character->name }}
                    @endif
                </td>
                <td style="min-width:400px">
                    <x-combo-link :combo="$combo" />
                    @if ($combo->comments || $combo->video)
                        <div class="collapse" id="collapse{{ $combo->idcombo }}">
                            {{ $combo->comments }}
                            <x-video-embed :video="$combo->video" />
                        </div>
                    @endif
                </td>
                <td>{{ number_format((float) $combo->damage, 0, '', '.') }}</td>
                <td>{{ $combo->listingType?->title }}</td>
            </tr>
        @endforeach
    </table>
@endforeach
