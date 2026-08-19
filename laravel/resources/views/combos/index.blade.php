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
                    <label class="form-label">Type</label>
                    <select name="listingtype" class="form-select">
                        <option value="-" @selected(request('listingtype', '-') === '-')>Show All</option>
                        @foreach ($listingTypes as $entry)
                            <option value="{{ $entry->entryid }}" @selected(request('listingtype') == $entry->entryid)>{{ $entry->title }}</option>
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
                <div class="col-auto">
                    <label class="form-label">The Combo</label>
                    <select name="combolike" class="form-select">
                        <option value="0" @selected(request('combolike', '0') === '0')>Starts with</option>
                        <option value="2" @selected(request('combolike') === '2')>Has</option>
                        <option value="1" @selected(request('combolike') === '1')>Ends with</option>
                        <option value="3" @selected(request('combolike') === '3')>Does not have</option>
                    </select>
                </div>
                <div class="col-auto flex-grow-1">
                    <label class="form-label">Notation</label>
                    <textarea name="combo" id="comboarea" class="form-control" rows="1">{{ request('combo') }}</textarea>
                </div>
            </div>

            <div class="mt-2">
                @foreach ($buttons as $button)
                    <button type="button" class="btn btn-sm" style="margin-left:0.25em;margin-bottom:0.5em;background-color: {{ $button->color }};" onclick="moveNumbers('{{ $button->name }}')">{{ $button->name }}</button>
                @endforeach
                <button type="button" class="btn btn-sm btn-secondary" onclick="backspace()">&#9003; Backspace</button>
            </div>

            <div class="row g-2 align-items-end mt-2">
                <div class="col-auto">
                    <label class="form-label">Max Damage</label>
                    <input type="number" name="damage" class="form-control" value="{{ request('damage') }}">
                </div>
                <div class="col-auto">
                    <label class="form-label">Patch</label>
                    <input type="text" name="patch" maxlength="10" class="form-control" value="{{ request('patch') }}">
                </div>
                <div class="col-auto">
                    <label class="form-label">Video contains</label>
                    <input type="text" name="video" id="video" class="form-control" value="{{ request('video') }}" @disabled(request()->boolean('novideo'))>
                </div>
                <div class="col-auto">
                    <div class="form-check">
                        <input type="checkbox" name="novideo" value="1" class="form-check-input" id="novideo" @checked(request()->boolean('novideo')) onchange="document.getElementById('video').disabled = this.checked;">
                        <label class="form-check-label" for="novideo">No video only</label>
                    </div>
                </div>
                <div class="col-auto">
                    <label class="form-label">Comment has (# separated)</label>
                    <input type="text" name="comments" class="form-control" placeholder="#universal #corner" value="{{ request('comments') }}">
                </div>
                <div class="col-auto">
                    <label class="form-label">Comment does not have</label>
                    <input type="text" name="notcomments" class="form-control" value="{{ request('notcomments') }}">
                </div>
            </div>

            @if ($primaryResources->isNotEmpty())
                <div class="row g-2 align-items-end mt-2">
                    @foreach ($primaryResources as $resource)
                        @php $field = str_replace(' ', '_', $resource->text_name); @endphp
                        <div class="col-auto">
                            <label class="form-label">{{ $resource->text_name }}</label>
                            @if ($resource->type === 1)
                                <select name="{{ $field }}" class="form-select">
                                    <option value="-">{{ $resource->text_name }}</option>
                                    @foreach ($resource->values->sortBy('order') as $value)
                                        <option value="{{ $value->idResources_values }}" @selected(request($field) == $value->idResources_values)>{{ $value->value }}</option>
                                    @endforeach
                                </select>
                            @elseif ($resource->type === 2)
                                @php $bound = $resource->values->first()?->value; @endphp
                                <div class="input-group">
                                    <select name="{{ $field }}compare" class="form-select">
                                        <option value="0" @selected(request($field.'compare', '0') === '0')>less than</option>
                                        <option value="1" @selected(request($field.'compare') === '1')>greater than</option>
                                        <option value="2" @selected(request($field.'compare') === '2')>equal to</option>
                                    </select>
                                    <input type="number" name="{{ $field }}" class="form-control" min="-{{ $bound }}" max="{{ $bound }}" step="any" value="{{ request($field) }}">
                                </div>
                            @else
                                <div class="input-group">
                                    @for ($i = 0; $i < 2; $i++)
                                        <select name="{{ $field }}[]" class="form-select">
                                            <option value="-">{{ $resource->text_name }}</option>
                                            @foreach ($resource->values->sortBy('order') as $value)
                                                <option value="{{ $value->idResources_values }}" @selected((request($field, [])[$i] ?? null) == $value->idResources_values)>{{ $value->value }}</option>
                                            @endforeach
                                        </select>
                                    @endfor
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

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
                            <a href="{{ route('combos.show', $combo) }}">{{ $combo->combo }}</a>
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
                                <td>{{ $matches->get(0)?->resourceValue?->value }}</td>
                                <td>{{ $matches->get(1)?->resourceValue?->value }}</td>
                            @else
                                <td>{{ $resource->type === 2 ? $matches->first()?->number_value : $matches->first()?->resourceValue?->value }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>

        {{ $combos->links() }}
    </div>
</x-layouts.app>
