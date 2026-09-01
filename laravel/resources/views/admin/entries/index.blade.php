<x-layouts.app :title="'Edit Entries - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <tr><th>Entry</th></tr>
            @foreach ($entries as $entry)
                <tr>
                    <td>
                        <form method="post" action="{{ route('admin.entries.store', $game) }}">
                            @csrf
                            <div class="input-group">
                                <textarea name="entry" maxlength="45" class="form-control" rows="1">{{ $entry->title }}</textarea>
                                <input class="form-control" type="number" name="order" value="{{ $entry->order }}" step="any">
                                <input type="hidden" name="entryid" value="{{ $entry->entryid }}">
                                <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
                                <button type="submit" name="action" value="Delete" class="btn btn-danger" data-confirm="Are you sure you want to delete this entry?">Delete</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td>
                    <form method="post" action="{{ route('admin.entries.store', $game) }}">
                        @csrf
                        <div class="input-group">
                            <textarea name="entry" maxlength="45" class="form-control" rows="1" placeholder="Entry Name" autofocus></textarea>
                            <input class="form-control" type="number" name="order" step="any">
                            <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                </td>
            </tr>
        </table>
        <p>Entry types are the way you categorize submissions, a few examples are: Combo, Blockstring, Okizeme...</p>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
