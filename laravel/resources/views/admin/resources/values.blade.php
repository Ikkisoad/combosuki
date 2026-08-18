<x-layouts.app :title="'Edit '.$resource->text_name.' Values - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <a href="{{ route('admin.resources.index', $game) }}" class="btn btn-secondary mb-3">&laquo; Back to Resources</a>

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <tr><th>{{ $resource->text_name }}</th></tr>
            @foreach ($values as $value)
                <tr>
                    <td>
                        <form method="post" action="{{ route('admin.resources.values.store', [$game, $resource]) }}">
                            @csrf
                            <div class="input-group">
                                <button class="btn btn-secondary" disabled>ID: {{ $value->idResources_values }}</button>
                                <textarea name="resourcevalue" maxlength="45" class="form-control" rows="1">{{ $value->value }}</textarea>
                                <input class="form-control" type="number" name="order" value="{{ $value->order }}" step="any">
                                <input type="hidden" name="idresourcevalue" value="{{ $value->idResources_values }}">
                                <button type="submit" name="action" value="EditUpdate" class="btn btn-primary">Update</button>
                                <button type="submit" name="action" value="EditDelete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this resource value?');">Delete</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td>
                    <form method="post" action="{{ route('admin.resources.values.store', [$game, $resource]) }}">
                        @csrf
                        <div class="input-group">
                            <textarea name="resourcevalue" maxlength="45" class="form-control" rows="1" placeholder="Resource Value" autofocus></textarea>
                            <input class="form-control" type="number" name="order" step="any">
                            <button type="submit" name="action" value="EditAdd" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                </td>
            </tr>
        </table>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
