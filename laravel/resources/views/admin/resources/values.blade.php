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

        @if ($resource->type === 2)
            @php $current = $values->first(); @endphp

            <form method="post" action="{{ route('admin.resources.values.store', [$game, $resource]) }}">
                @csrf
                <label class="form-label text-white">{{ $resource->text_name }} &mdash; max number for this resource</label>
                <div class="input-group">
                    <input type="number" name="resourcevalue" class="form-control" step="any" value="{{ old('resourcevalue', $current->value ?? '') }}" required>
                    <input type="hidden" name="order" value="{{ $current->order ?? 0 }}">
                    @if ($current)
                        <input type="hidden" name="idresourcevalue" value="{{ $current->idResources_values }}">
                        <button type="submit" name="action" value="EditUpdate" class="btn btn-primary">Save</button>
                    @else
                        <button type="submit" name="action" value="EditAdd" class="btn btn-primary">Save</button>
                    @endif
                </div>
            </form>
        @else
            <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                <tr><th>{{ $resource->text_name }}</th></tr>
                @foreach ($values as $value)
                    <tr>
                        <td>
                            <form method="post" action="{{ route('admin.resources.values.store', [$game, $resource]) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="input-group">
                                    <button class="btn btn-secondary" disabled>ID: {{ $value->idResources_values }}</button>
                                    <span class="input-group-text p-1">
                                        <x-resource-value-icon :value="$value" />
                                    </span>
                                    <textarea name="resourcevalue" maxlength="45" class="form-control" rows="1">{{ $value->value }}</textarea>
                                    <input class="form-control" type="number" name="order" value="{{ $value->order }}" step="any">
                                    <input type="file" name="icon" accept="image/*" class="form-control" title="Icon (optional)" style="max-width: 200px;">
                                    <input type="hidden" name="idresourcevalue" value="{{ $value->idResources_values }}">
                                    <button type="submit" name="action" value="EditUpdate" class="btn btn-primary">Update</button>
                                    <button type="submit" name="action" value="EditDelete" class="btn btn-danger" data-confirm="Are you sure you want to delete this resource value?">Delete</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td>
                        <form method="post" action="{{ route('admin.resources.values.store', [$game, $resource]) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="input-group">
                                <textarea name="resourcevalue" maxlength="45" class="form-control" rows="1" placeholder="Resource Value" autofocus></textarea>
                                <input class="form-control" type="number" name="order" step="any">
                                <input type="file" name="icon" accept="image/*" class="form-control" title="Icon (optional)" style="max-width: 200px;">
                                <button type="submit" name="action" value="EditAdd" class="btn btn-primary">Add</button>
                            </div>
                        </form>
                    </td>
                </tr>
            </table>
        @endif

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
