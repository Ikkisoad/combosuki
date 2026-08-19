@php
    $buttons = $game->buttons()->orderBy('order')->get();
@endphp

<x-layouts.app :title="'Edit Combo - '.$game->name">
    <x-jumbotron :height="200" />
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        <h2>Edit combo</h2>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('combos.update', $combo) }}">
            @csrf

            <div class="input-group mb-3">
                <label class="input-group-text">Character:</label>
                <select name="character_idcharacter" class="form-select" required>
                    @foreach ($characters as $character)
                        <option value="{{ $character->idcharacter }}" @selected(old('character_idcharacter', $combo->character_idcharacter) == $character->idcharacter)>{{ $character->name }}</option>
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
                      placeholder="{{ $game->notation }}" required>{{ old('combo', $combo->combo) }}</textarea>

            <a href="https://github.com/Ikkisoad/combosuki/issues" target="_blank">Is something missing?</a>

            <div class="row my-3">
                <div class="col">
                    <div class="input-group mb-3">
                        <span class="input-group-text">Damage:</span>
                        <input class="form-control" type="number" name="damage" min="0" value="{{ old('damage', $combo->damage) }}">
                    </div>
                </div>
                <div class="col">
                    <div class="input-group mb-3">
                        <span class="input-group-text">Patch:</span>
                        <input type="text" name="patch" class="form-control" value="{{ old('patch', $combo->patch) }}">
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
                                    <select name="resources[{{ $resource->idgame_resources }}]" class="form-select">
                                        <option value="-">-</option>
                                        @foreach ($resource->values->sortBy('order') as $value)
                                            <option value="{{ $value->idResources_values }}" @selected(($selectedResources[$resource->idgame_resources]['value_id'] ?? null) == $value->idResources_values)>{{ $value->value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                @php $bound = $resource->values->first()?->value; @endphp
                                <div class="input-group mb-3 flex-nowrap">
                                    <span class="input-group-text">{{ $resource->text_name }}</span>
                                    <input class="form-control" type="number" name="resources[{{ $resource->idgame_resources }}]"
                                           max="{{ $bound }}" min="-{{ $bound }}" step="any"
                                           value="{{ $selectedResources[$resource->idgame_resources]['number_value'] ?? '' }}">
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($resources->where('primaryORsecundary', 0)->isNotEmpty())
                <h3>Secondary Resources:</h3>
                <div class="row align-items-center">
                    @foreach ($resources->where('primaryORsecundary', 0) as $resource)
                        <div class="col">
                            @if ($resource->type === 1)
                                <div class="input-group mb-3 flex-nowrap">
                                    <label class="input-group-text">{{ $resource->text_name }}</label>
                                    <select name="resources[{{ $resource->idgame_resources }}]" class="form-select">
                                        <option value="-">-</option>
                                        @foreach ($resource->values->sortBy('order') as $value)
                                            <option value="{{ $value->idResources_values }}" @selected(($selectedResources[$resource->idgame_resources]['value_id'] ?? null) == $value->idResources_values)>{{ $value->value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                @php $bound = $resource->values->first()?->value; @endphp
                                <div class="input-group mb-3 flex-nowrap">
                                    <span class="input-group-text">{{ $resource->text_name }}</span>
                                    <input class="form-control" type="number" name="resources[{{ $resource->idgame_resources }}]"
                                           max="{{ $bound }}" min="-{{ $bound }}" step="any"
                                           value="{{ $selectedResources[$resource->idgame_resources]['number_value'] ?? '' }}">
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="row">
                <div class="col">
                    <label>Comments:</label>
                    <textarea name="comments" class="form-control" placeholder="Comments like: Corner only, universal, etc... are recommended to make it easier to search specific situations.">{{ old('comments', $combo->comments) }}</textarea>

                    <label class="mt-2">Video:</label>
                    <textarea name="video" class="form-control" rows="1" maxlength="255"
                              placeholder="Currently supports YouTube, Twitter/X, Streamable, Twitch clips, Imgur, Niconico, Gfycat and MedalTv.">{{ old('video', $combo->video) }}</textarea>
                </div>
            </div>

            <div class="row">
                <div class="col my-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="{{ route('combos.show', $combo) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>

        <form method="post" action="{{ route('combos.destroy', $combo) }}" onsubmit="return confirm('Are you sure you want to delete this combo?');">
            @csrf
            <button type="submit" class="btn btn-danger">Delete Combo</button>
        </form>
    </div>
</x-layouts.app>
