@php
    $buttons = $game->buttons()->orderBy('order')->get();
@endphp

<x-layouts.app :title="'Search Combos - '.$game->name">
    <x-jumbotron :height="100" />
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        <form method="get" action="{{ route('games.combos.index', $game) }}" class="card combosuki-main-reversed text-white p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label">Character</label>
                    <select name="characterid" class="form-select">
                        <option value="-">Character</option>
                        @foreach ($characters as $character)
                            <option value="{{ $character->idcharacter }}" @selected(request('characterid') == $character->idcharacter)>{{ $character->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label">Order By</label>
                    <select name="Submitted" class="form-select">
                        <option value="-" @selected(request('Submitted', '-') === '-')>-</option>
                        <option value="0" @selected(request('Submitted') === '0')>Newest</option>
                        <option value="1" @selected(request('Submitted') === '1')>Oldest</option>
                    </select>
                </div>
            </div>

            @include('combos.partials.filter-fields', [
                'values' => request()->all(),
                'buttons' => $buttons,
                'primaryResources' => $primaryResources,
                'listingTypes' => $listingTypes,
                'notationId' => 'comboarea',
            ])

            <div class="mt-3">
                <button type="submit" class="btn btn-info">Search</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                <caption>{{ $combos->total() }} result(s)</caption>
                <tr>
                    <th>Character</th>
                    <th>Inputs</th>
                    <th>Damage</th>
                    @foreach ($primaryResources as $resource)
                        <th>{{ $resource->text_name }}</th>
                        @if ($resource->type === 3)
                            <th>{{ $resource->text_name }}</th>
                        @endif
                    @endforeach
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
                        @php
                            $comboResourcesByGameResource = $combo->resources->groupBy(fn ($r) => $r->resourceValue?->game_resources_idgame_resources);
                        @endphp
                        @foreach ($primaryResources as $resource)
                            @php $matches = $comboResourcesByGameResource->get($resource->idgame_resources, collect()); @endphp
                            @if ($resource->type === 3)
                                <td><x-resource-value-icon :value="$matches->get(0)?->resourceValue" />{{ $matches->get(0)?->resourceValue?->value }}</td>
                                <td><x-resource-value-icon :value="$matches->get(1)?->resourceValue" />{{ $matches->get(1)?->resourceValue?->value }}</td>
                            @elseif ($resource->type === 2)
                                <td>{{ $matches->first()?->number_value }}</td>
                            @else
                                <td><x-resource-value-icon :value="$matches->first()?->resourceValue" />{{ $matches->first()?->resourceValue?->value }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>

        {{ $combos->links() }}
    </div>
</x-layouts.app>
