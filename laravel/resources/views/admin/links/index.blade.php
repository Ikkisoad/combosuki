<x-layouts.app :title="'Edit Links - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <tr><th>Link</th></tr>
            @foreach ($links as $link)
                <tr>
                    <td>
                        <form method="post" action="{{ route('admin.links.store', $game) }}">
                            @csrf
                            <div class="input-group">
                                <textarea name="title" maxlength="50" class="form-control" rows="1">{{ $link->Title }}</textarea>
                                <textarea name="link" maxlength="255" class="form-control" rows="1">{{ $link->Link }}</textarea>
                                <input type="hidden" name="idLink" value="{{ $link->idLink }}">
                                <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
                                <button type="submit" name="action" value="Delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this link?');">Delete</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td>
                    <form method="post" action="{{ route('admin.links.store', $game) }}">
                        @csrf
                        <div class="input-group">
                            <textarea name="title" maxlength="50" class="form-control" rows="1" placeholder="Link Title" autofocus></textarea>
                            <textarea name="link" maxlength="255" class="form-control" rows="1" placeholder="Link URL"></textarea>
                            <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                </td>
            </tr>
        </table>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
