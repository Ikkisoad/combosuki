<x-layouts.app :title="'Edit Queries - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p>
            These queries are evaluated for every character in this game to build their character page: the
            highest-damage combo matching each query is shown as that character's "top combo" for it.
        </p>

        @foreach ($queries as $query)
            <form method="post" action="{{ route('admin.queries.store', $game) }}" class="card combosuki-main-reversed text-white p-3 mb-3" data-query-id="{{ $query->idquery }}">
                @csrf
                <input type="hidden" name="idquery" value="{{ $query->idquery }}">
                <div class="row g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label">Label</label>
                        <input type="text" name="label" maxlength="150" class="form-control" value="{{ $query->label }}">
                    </div>
                    <div class="col-auto">
                        <label class="form-label">Order</label>
                        <input type="number" name="order" class="form-control" value="{{ $query->order }}">
                    </div>
                </div>

                @include('combos.partials.filter-fields', [
                    'values' => $query->filters ?? [],
                    'buttons' => $buttons,
                    'primaryResources' => $primaryResources,
                    'listingTypes' => $listingTypes,
                    'hideDamageAndVideo' => true,
                ])

                <div class="mt-3">
                    <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
                    <button type="submit" name="action" value="Delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this query?');">Delete</button>
                </div>
            </form>
        @endforeach

        <form method="post" action="{{ route('admin.queries.store', $game) }}" class="card combosuki-main-reversed text-white p-3 mb-3">
            @csrf
            <div class="row g-2 align-items-end">
                @if ($queries->isNotEmpty())
                    <div class="col-auto">
                        <label class="form-label">Copy from</label>
                        <select class="form-select" onchange="copyQueryInto(this)">
                            <option value="">— none —</option>
                            @foreach ($queries as $query)
                                <option value="{{ $query->idquery }}">{{ $query->label !== '' ? $query->label : 'Query #'.$query->idquery }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-auto">
                    <label class="form-label">Label</label>
                    <input type="text" name="label" maxlength="150" class="form-control" placeholder="e.g. 2LK starter, no meter" autofocus>
                </div>
                <div class="col-auto">
                    <label class="form-label">Order</label>
                    <input type="number" name="order" class="form-control" value="0">
                </div>
            </div>

            @include('combos.partials.filter-fields', [
                'values' => [],
                'buttons' => $buttons,
                'primaryResources' => $primaryResources,
                'listingTypes' => $listingTypes,
                'hideDamageAndVideo' => true,
            ])

            <div class="mt-3">
                <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
            </div>
        </form>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
