@php
    $buttons = $game->buttons()->orderBy('order')->get();
@endphp

<x-layouts.app :title="'Add Combo - '.$game->name">
    <x-jumbotron :height="200" />
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        <h2>Submit a combo</h2>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('games.combos.store', $game) }}">
            @csrf

            <div class="input-group mb-3">
                <label class="input-group-text">Character:</label>
                <select name="character_idcharacter" class="form-select" required onchange="filterSecondaryResources()">
                    @foreach ($characters as $character)
                        <option value="{{ $character->idcharacter }}" @selected(old('character_idcharacter', $defaults['character_idcharacter'] ?? null) == $character->idcharacter)>{{ $character->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="input-group mb-3">
                <label class="input-group-text">Type:</label>
                <select name="listingtype" class="form-select" required>
                    @foreach ($listingTypes as $entry)
                        <option value="{{ $entry->entryid }}" @selected(old('listingtype', $defaults['listingtype'] ?? null) == $entry->entryid)>{{ $entry->title }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-2">
                @foreach ($buttons as $button)
                    <button type="button" class="btn btn-sm" style="margin-left:0.25em;margin-bottom:0.5em;background-color: {{ $button->color }};" onclick="moveNumbers('{{ $button->name }}')">{{ $button->name }}</button>
                @endforeach
                <button type="button" class="btn btn-sm btn-secondary" onclick="backspace()">&#9003; Backspace</button>
            </div>

            <textarea name="combo" class="form-control" id="comboarea" rows="4"
                      placeholder="{{ $game->notation }}" required>{{ old('combo', $defaults['combo'] ?? '') }}</textarea>

            <a href="https://github.com/Ikkisoad/combosuki/issues" target="_blank">Is something missing?</a>

            <div class="row my-3">
                <div class="col">
                    <div class="input-group mb-3">
                        <span class="input-group-text">Damage:</span>
                        <input class="form-control" type="number" name="damage" min="0" value="{{ old('damage', $defaults['damage'] ?? '') }}">
                    </div>
                </div>
                <div class="col">
                    <div class="input-group mb-3">
                        <span class="input-group-text">Patch:</span>
                        <input type="text" name="patch" class="form-control" value="{{ old('patch', $defaults['patch'] ?? $game->patch) }}">
                    </div>
                </div>
            </div>

            @if ($resources->where('primaryORsecundary', 1)->isNotEmpty())
                <div class="row align-items-center">
                    @foreach ($resources->where('primaryORsecundary', 1) as $resource)
                        <div class="col">
                            @if ($resource->type === 1)
                                <div class="input-group mb-3 flex-nowrap">
                                    <label class="input-group-text">{{ $resource->text_name }}</label>
                                    <select name="resources[{{ $resource->idgame_resources }}]" class="form-select" required>
                                        @foreach ($resource->values->sortBy([['order', 'asc'], ['value', 'asc']]) as $value)
                                            <option value="{{ $value->idResources_values }}" @selected(old("resources.{$resource->idgame_resources}", $defaults['resources'][$resource->idgame_resources] ?? null) == $value->idResources_values)>{{ $value->value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif ($resource->type === 3)
                                <div class="input-group mb-3 flex-nowrap">
                                    <label class="input-group-text">{{ $resource->text_name }}</label>
                                    @for ($slot = 0; $slot < 2; $slot++)
                                        <select name="resources[{{ $resource->idgame_resources }}][]" class="form-select" required>
                                            @foreach ($resource->values->sortBy([['order', 'asc'], ['value', 'asc']]) as $value)
                                                <option value="{{ $value->idResources_values }}" @selected(old("resources.{$resource->idgame_resources}.{$slot}", $defaults['resources'][$resource->idgame_resources][$slot] ?? null) == $value->idResources_values)>{{ $value->value }}</option>
                                            @endforeach
                                        </select>
                                    @endfor
                                </div>
                            @else
                                @php $bound = $resource->values->first()?->value; @endphp
                                <div class="input-group mb-3 flex-nowrap">
                                    <span class="input-group-text">{{ $resource->text_name }}</span>
                                    <input class="form-control" type="number" name="resources[{{ $resource->idgame_resources }}]"
                                           max="{{ $bound }}" min="-{{ $bound }}" step="any" required
                                           value="{{ old("resources.{$resource->idgame_resources}", $defaults['resources'][$resource->idgame_resources] ?? 0) }}">
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($resources->where('primaryORsecundary', 0)->isNotEmpty())
                <div class="d-flex align-items-center gap-2">
                    <h3 class="mb-0">Secondary Resources:</h3>
                    @if ($resources->where('primaryORsecundary', 0)->contains(fn ($resource) => $resource->characters->isNotEmpty()))
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-secondary-resources" onclick="toggleSecondaryResources()">Show all secondary resources</button>
                    @endif
                </div>
                <div class="row align-items-center">
                    @foreach ($resources->where('primaryORsecundary', 0) as $resource)
                        <div class="col secondary-resource-col" data-characters="{{ $resource->characters->pluck('idcharacter')->implode(',') }}">
                            @if ($resource->type === 1)
                                <div class="input-group mb-3 flex-nowrap">
                                    <label class="input-group-text">{{ $resource->text_name }}</label>
                                    <select name="resources[{{ $resource->idgame_resources }}]" class="form-select">
                                        <option value="-">-</option>
                                        @foreach ($resource->values->sortBy([['order', 'asc'], ['value', 'asc']]) as $value)
                                            <option value="{{ $value->idResources_values }}" @selected(old("resources.{$resource->idgame_resources}", $defaults['resources'][$resource->idgame_resources] ?? null) == $value->idResources_values)>{{ $value->value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif ($resource->type === 3)
                                <div class="input-group mb-3 flex-nowrap">
                                    <label class="input-group-text">{{ $resource->text_name }}</label>
                                    @for ($slot = 0; $slot < 2; $slot++)
                                        <select name="resources[{{ $resource->idgame_resources }}][]" class="form-select">
                                            <option value="-">-</option>
                                            @foreach ($resource->values->sortBy([['order', 'asc'], ['value', 'asc']]) as $value)
                                                <option value="{{ $value->idResources_values }}" @selected(old("resources.{$resource->idgame_resources}.{$slot}", $defaults['resources'][$resource->idgame_resources][$slot] ?? null) == $value->idResources_values)>{{ $value->value }}</option>
                                            @endforeach
                                        </select>
                                    @endfor
                                </div>
                            @else
                                @php $bound = $resource->values->first()?->value; @endphp
                                <div class="input-group mb-3 flex-nowrap">
                                    <span class="input-group-text">{{ $resource->text_name }}</span>
                                    <input class="form-control" type="number" name="resources[{{ $resource->idgame_resources }}]"
                                           max="{{ $bound }}" min="-{{ $bound }}" step="any"
                                           value="{{ old("resources.{$resource->idgame_resources}", $defaults['resources'][$resource->idgame_resources] ?? '') }}">
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="row">
                <div class="col">
                    <label>Comments:</label>
                    <textarea name="comments" class="form-control" placeholder="Comments like: Corner only, universal, etc... are recommended to make it easier to search specific situations.">{{ old('comments', $defaults['comments'] ?? '') }}</textarea>

                    <label class="mt-2">Video:</label>
                    <textarea name="video" class="form-control" rows="1" maxlength="255"
                              placeholder="Currently supports YouTube, Twitter/X, Streamable, Twitch clips, Imgur, Niconico, Gfycat and MedalTv.">{{ old('video', $defaults['video'] ?? '') }}</textarea>
                </div>
            </div>

            <div class="row">
                <div class="col my-3">
                    <button type="submit" class="btn btn-primary btn-block">Submit</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => initSecondaryResources(false));
    </script>
</x-layouts.app>
