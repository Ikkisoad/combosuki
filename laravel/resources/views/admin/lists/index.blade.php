<x-layouts.app :title="'Guides - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <tr><th>Guide</th></tr>
            @foreach ($lists as $list)
                <tr>
                    <td>
                        <form method="post" action="{{ route('admin.lists.store', $game) }}">
                            @csrf
                            <div class="input-group">
                                <a href="{{ route('lists.show', $list) }}" class="btn btn-secondary">{{ $list->list_name }}</a>
                                <textarea name="listname" maxlength="100" class="form-control" rows="1" placeholder="Rename to..."></textarea>
                                <select name="type" class="form-select">
                                    <option value="1" @selected($list->type === 1)>Normal</option>
                                    <option value="2" @selected($list->type === 2)>Verified</option>
                                    <option value="3" @selected($list->type === 3)>Featured</option>
                                    <option value="0" @selected($list->type === 0)>Hidden</option>
                                </select>
                                <input type="hidden" name="idlist" value="{{ $list->idlist }}">
                                <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
                                <button type="submit" name="action" value="Delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this list?');">Delete</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endforeach
        </table>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
