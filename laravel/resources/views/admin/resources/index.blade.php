<x-layouts.app :title="'Edit Resources - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <tr><th>Resource</th></tr>
            @foreach ($resources as $resource)
                <tr>
                    <td>
                        <form method="post" action="{{ route('admin.resources.store', $game) }}" class="d-inline">
                            @csrf
                            <div class="input-group">
                                <textarea name="resource" maxlength="45" class="form-control" rows="1">{{ $resource->text_name }}</textarea>
                                <select name="type" class="form-select">
                                    <option value="1" @selected($resource->type === 1)>List</option>
                                    <option value="2" @selected($resource->type === 2)>Number</option>
                                    <option value="3" @selected($resource->type === 3)>Duplicated</option>
                                </select>
                                <select name="primaryORsecundary" class="form-select">
                                    <option value="1" @selected($resource->primaryORsecundary === 1)>Primary</option>
                                    <option value="0" @selected($resource->primaryORsecundary !== 1)>Secondary</option>
                                </select>
                                <input name="gamePass" type="password" maxlength="16" class="form-control" placeholder="Game Password">
                                <input type="hidden" name="idresource" value="{{ $resource->idgame_resources }}">
                                <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
                                <button type="submit" name="action" value="Delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this resource?');">Delete</button>
                            </div>
                        </form>
                        <a href="{{ route('admin.resources.values', [$game, $resource]) }}" class="btn btn-secondary mt-1">Edit values</a>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td>
                    <form method="post" action="{{ route('admin.resources.store', $game) }}">
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
                            <input name="gamePass" type="password" maxlength="16" class="form-control" placeholder="Game Password">
                            <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                </td>
            </tr>
        </table>

        <p>
            Any game needs at least ONE primary resource for it to work properly (that includes searching/viewing submissions).<br>
            Primary resources are the ones that always have to be listed along submissions, secondary are more specific resources that do not have to be on every entry.<br>
            Currently there are three types of resources:<br>
            1: List &mdash; a simple list of options, it should have at least one option to work properly.<br>
            2: Number &mdash; in its options, number resources should have its max value.<br>
            3: Duplicated &mdash; for games that have assists, duplicated resources appear twice and allow searches to ignore the order of the assists.
        </p>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
