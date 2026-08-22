<x-layouts.app :title="'Edit Characters - '.$game->name">
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

        <form id="bulk-characters-form" method="post" action="{{ route('admin.characters.bulkUpdate', $game) }}" enctype="multipart/form-data">
            @csrf
        </form>

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <caption>Edit the fields below and click "Save All" to update every character at once.</caption>
            <tr><th>Character</th></tr>
            @foreach ($characters as $character)
                <tr>
                    <td>
                        <div class="input-group">
                            @if ($character->image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($character->image) }}" alt="{{ $character->name }}" style="height:38px;width:38px;object-fit:cover;">
                            @endif
                            <textarea form="bulk-characters-form" name="characters[{{ $character->idcharacter }}][name]" maxlength="45" class="form-control" rows="1">{{ $character->name }}</textarea>
                            <input form="bulk-characters-form" type="file" name="characters[{{ $character->idcharacter }}][image]" accept="image/*" class="form-control">
                            <form method="post" action="{{ route('admin.characters.store', $game) }}">
                                @csrf
                                <input type="hidden" name="idcharacter" value="{{ $character->idcharacter }}">
                                <button type="submit" name="action" value="Delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this character?');">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td>
                    <button type="submit" form="bulk-characters-form" class="btn btn-primary">Save All</button>
                </td>
            </tr>
            <tr>
                <td>
                    <form method="post" action="{{ route('admin.characters.store', $game) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="input-group">
                            <textarea name="character" maxlength="45" class="form-control" rows="1" placeholder="Character Name" autofocus></textarea>
                            <input type="file" name="image" accept="image/*" class="form-control">
                            <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                </td>
            </tr>
        </table>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
