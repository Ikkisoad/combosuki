{{-- Shared combo-search filter fields, driven by $values (a flat map using
     the same field names FiltersCombos::applyFilters() consumes) instead of
     request() directly, so this can render either the live search form
     (values = request()->all()) or a stored default query's filters.
     Expects: $values, $buttons, $primaryResources, $listingTypes, and an
     optional $notationId to wire up the per-game notation buttons/backspace
     helper (only meaningful when exactly one instance of this partial is on
     the page, since that JS targets a single element id). --}}

<div class="row g-2 align-items-end mt-2">
    <div class="col-auto">
        <label class="form-label">Type</label>
        <select name="listingtype" class="form-select">
            <option value="-" @selected(($values['listingtype'] ?? '-') === '-')>Show All</option>
            @foreach ($listingTypes as $entry)
                <option value="{{ $entry->entryid }}" @selected(($values['listingtype'] ?? null) == $entry->entryid)>{{ $entry->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label">The Combo</label>
        <select name="combolike" class="form-select">
            <option value="0" @selected(($values['combolike'] ?? '0') === '0')>Starts with</option>
            <option value="2" @selected(($values['combolike'] ?? null) === '2')>Has</option>
            <option value="1" @selected(($values['combolike'] ?? null) === '1')>Ends with</option>
            <option value="3" @selected(($values['combolike'] ?? null) === '3')>Does not have</option>
        </select>
    </div>
    <div class="col-auto flex-grow-1">
        <label class="form-label">Notation</label>
        <textarea name="combo" @if ($notationId ?? null) id="{{ $notationId }}" @endif class="form-control" rows="1">{{ $values['combo'] ?? '' }}</textarea>
    </div>
</div>

@if ($notationId ?? null)
    <div class="mt-2">
        @foreach ($buttons as $button)
            <button type="button" class="btn btn-sm" style="margin-left:0.25em;margin-bottom:0.5em;background-color: {{ $button->color }};" onclick="moveNumbers('{{ $button->name }}')">{{ $button->name }}</button>
        @endforeach
        <button type="button" class="btn btn-sm btn-secondary" onclick="backspace()">&#9003; Backspace</button>
    </div>
@endif

<div class="row g-2 align-items-end mt-2">
    <div class="col-auto">
        <label class="form-label">Max Damage</label>
        <input type="number" name="damage" class="form-control" value="{{ $values['damage'] ?? '' }}">
    </div>
    <div class="col-auto">
        <label class="form-label">Patch</label>
        <input type="text" name="patch" maxlength="10" class="form-control" value="{{ $values['patch'] ?? '' }}">
    </div>
    <div class="col-auto">
        <label class="form-label">Video contains</label>
        <input type="text" name="video" class="form-control" value="{{ $values['video'] ?? '' }}" @disabled($values['novideo'] ?? false)>
    </div>
    <div class="col-auto">
        <div class="form-check">
            <input type="checkbox" name="novideo" value="1" class="form-check-input" @checked($values['novideo'] ?? false) onchange="const v = this.closest('.row').querySelector('[name=video]'); if (v) v.disabled = this.checked;">
            <label class="form-check-label">No video only</label>
        </div>
    </div>
    <div class="col-auto">
        <label class="form-label">Comment has (# separated)</label>
        <input type="text" name="comments" class="form-control" placeholder="#universal #corner" value="{{ $values['comments'] ?? '' }}">
    </div>
    <div class="col-auto">
        <label class="form-label">Comment does not have</label>
        <input type="text" name="notcomments" class="form-control" value="{{ $values['notcomments'] ?? '' }}">
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
                            <option value="{{ $value->idResources_values }}" @selected(($values[$field] ?? null) == $value->idResources_values)>{{ $value->value }}</option>
                        @endforeach
                    </select>
                @elseif ($resource->type === 2)
                    @php $bound = $resource->values->first()?->value; @endphp
                    <div class="input-group">
                        <select name="{{ $field }}compare" class="form-select">
                            <option value="0" @selected(($values[$field.'compare'] ?? '0') === '0')>less than</option>
                            <option value="1" @selected(($values[$field.'compare'] ?? null) === '1')>greater than</option>
                            <option value="2" @selected(($values[$field.'compare'] ?? null) === '2')>equal to</option>
                        </select>
                        <input type="number" name="{{ $field }}" class="form-control" min="-{{ $bound }}" max="{{ $bound }}" step="any" value="{{ $values[$field] ?? '' }}">
                    </div>
                @else
                    <div class="input-group">
                        @for ($i = 0; $i < 2; $i++)
                            <select name="{{ $field }}[]" class="form-select">
                                <option value="-">{{ $resource->text_name }}</option>
                                @foreach ($resource->values->sortBy('order') as $value)
                                    <option value="{{ $value->idResources_values }}" @selected((($values[$field] ?? [])[$i] ?? null) == $value->idResources_values)>{{ $value->value }}</option>
                                @endforeach
                            </select>
                        @endfor
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
