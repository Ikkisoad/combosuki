<x-layouts.app :title="'Edit Resources - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @foreach ($resources as $resource)
            <form id="delete-resource-{{ $resource->idgame_resources }}" method="post" action="{{ route('admin.resources.store', $game) }}" class="d-none">
                @csrf
                <input type="hidden" name="action" value="Delete">
                <input type="hidden" name="idresource" value="{{ $resource->idgame_resources }}">
            </form>
        @endforeach

        <form method="post" action="{{ route('admin.resources.store', $game) }}">
            @csrf
            <input type="hidden" name="action" value="SaveAll">

            <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                <tr><th>Resource</th></tr>
                @foreach ($resources as $resource)
                    <tr>
                        <td>
                            <div class="input-group">
                                <textarea name="resources[{{ $resource->idgame_resources }}][resource]" maxlength="45" class="form-control" rows="1">{{ $resource->text_name }}</textarea>
                                <select name="resources[{{ $resource->idgame_resources }}][type]" class="form-select">
                                    <option value="1" @selected($resource->type === 1)>List</option>
                                    <option value="2" @selected($resource->type === 2)>Number</option>
                                    <option value="3" @selected($resource->type === 3)>Duplicated</option>
                                </select>
                                <select name="resources[{{ $resource->idgame_resources }}][primaryORsecundary]" class="form-select">
                                    <option value="1" @selected($resource->primaryORsecundary === 1)>Primary</option>
                                    <option value="0" @selected($resource->primaryORsecundary !== 1)>Secondary</option>
                                </select>
                                <select name="resources[{{ $resource->idgame_resources }}][characters][]" class="form-select" multiple size="4" style="max-width: 200px;" title="Characters this resource applies to (leave empty for every character)">
                                    @foreach ($characters as $character)
                                        <option value="{{ $character->idcharacter }}" @selected($resource->characters->contains('idcharacter', $character->idcharacter))>{{ $character->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" form="delete-resource-{{ $resource->idgame_resources }}" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this resource?');">Delete</button>
                            </div>
                            <a href="{{ route('admin.resources.values', [$game, $resource]) }}" class="btn btn-secondary mt-1">Edit values</a>
                        </td>
                    </tr>
                @endforeach
            </table>

            <button type="submit" class="btn btn-primary">Save all</button>
        </form>

        <form method="post" action="{{ route('admin.resources.store', $game) }}" class="mt-3">
            @csrf
            <div class="input-group">
                <textarea name="resource" maxlength="45" class="form-control" rows="1" placeholder="Resource Name" autofocus></textarea>
                <select name="type" class="form-select">
                    <option value="1">List</option>
                    <option value="2">Number</option>
                    <option value="3">Duplicated</option>
                </select>
                <select name="primaryORsecundary" class="form-select">
                    <option value="1">Primary</option>
                    <option value="0">Secondary</option>
                </select>
                <select name="characters[]" class="form-select" multiple size="4" style="max-width: 200px;" title="Characters this resource applies to (leave empty for every character)">
                    @foreach ($characters as $character)
                        <option value="{{ $character->idcharacter }}">{{ $character->name }}</option>
                    @endforeach
                </select>
                <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
            </div>
        </form>

        <p>
            Any game needs at least ONE primary resource for it to work properly (that includes searching/viewing submissions).<br>
            Primary resources are the ones that always have to be listed along submissions, secondary are more specific resources that do not have to be on every entry.<br>
            Currently there are three types of resources:<br>
            1: List &mdash; a simple list of options, it should have at least one option to work properly.<br>
            2: Number &mdash; in its options, number resources should have its max value.<br>
            3: Duplicated &mdash; for games that have assists, duplicated resources appear twice and allow searches to ignore the order of the assists.<br>
            Leave a resource's Characters selection empty to show it for every character; select one or more to only show it on the combo form by default for those characters (a button on the combo form can still reveal it for any character).
        </p>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
