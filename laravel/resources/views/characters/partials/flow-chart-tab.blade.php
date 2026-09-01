<h4 class="mb-2">Combo Flow Chart</h4>

<p class="text-white-50 small">Click a move to build a path through {{ $character->name }}'s submitted combos &mdash; the options shown are always moves that continue a real combo starting with exactly what you've picked so far, not just any move that's ever followed the current one. Click one to step forward, or click back onto an earlier move on the path to jump back to it.</p>

<form id="flow-chart-filters" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <label for="flow-chart-filter-type" class="form-label small mb-0">Type</label>
        <select id="flow-chart-filter-type" name="listingtype" class="form-select form-select-sm">
            <option value="-">Show All</option>
            @foreach ($listingTypes as $entry)
                <option value="{{ $entry->entryid }}" @selected(($filters['listingtype'] ?? '-') == $entry->entryid)>{{ $entry->title }}</option>
            @endforeach
        </select>
    </div>
    @foreach ($primaryResources as $resource)
        @php $field = str_replace(' ', '_', $resource->text_name); @endphp
        <div class="col-auto">
            <label class="form-label small mb-0">{{ $resource->text_name }}</label>
            @if ($resource->type === 1)
                <select name="{{ $field }}" class="form-select form-select-sm">
                    <option value="-">Any</option>
                    @foreach ($resource->values->sortBy([['order', 'asc'], ['value', 'asc']]) as $value)
                        <option value="{{ $value->idResources_values }}" @selected(($filters[$field] ?? '-') == $value->idResources_values)>{{ $value->value }}</option>
                    @endforeach
                </select>
            @elseif ($resource->type === 2)
                @php $bound = $resource->values->first()?->value; @endphp
                <input type="number" name="{{ $field }}" class="form-control form-control-sm" min="-{{ $bound }}" max="{{ $bound }}" step="any" value="{{ $filters[$field] ?? '' }}">
            @else
                <div class="d-flex gap-1">
                    @for ($i = 0; $i < 2; $i++)
                        <select name="{{ $field }}[]" class="form-select form-select-sm d-inline-block w-auto">
                            <option value="-">Any</option>
                            @foreach ($resource->values->sortBy([['order', 'asc'], ['value', 'asc']]) as $value)
                                <option value="{{ $value->idResources_values }}" @selected((($filters[$field] ?? [])[$i] ?? '-') == $value->idResources_values)>{{ $value->value }}</option>
                            @endforeach
                        </select>
                    @endfor
                </div>
            @endif
        </div>
    @endforeach
    <div class="col-auto">
        <button type="submit" class="btn btn-info btn-sm">Apply Filters</button>
    </div>
</form>

@if (empty($starters))
    <p>No combos match the current filters.</p>
@else
    <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
        <p id="flow-chart-output" class="mb-0 flex-grow-1"><span class="text-white-50">Click a starting move below&hellip;</span></p>
        <button type="button" id="flow-chart-reset" class="btn btn-secondary btn-sm">Reset path</button>
    </div>

    <div id="combo-flow-chart" data-next-endpoint="{{ route('characters.tabs.flow-chart.next', [$game, $character]) }}" style="height: 600px; background: #1a1a1a; border-radius: 4px;"></div>

    <h5 class="mt-3 mb-2">Matching Combos</h5>
    <div id="flow-chart-matches" data-endpoint="{{ route('characters.tabs.flow-chart.matches', [$game, $character]) }}">
        <p class="text-white-50 small mb-0">Click a move above to see which existing combos start with it.</p>
    </div>

    <script id="flow-chart-data" type="application/json">{!! json_encode(['moves' => $starters]) !!}</script>
@endif
