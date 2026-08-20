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

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <tr><th>Character</th></tr>
            @foreach ($characters as $character)
                <tr>
                    <td>
                        <form method="post" action="{{ route('admin.characters.store', $game) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="input-group">
                                @if ($character->image)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($character->image) }}" alt="{{ $character->name }}" style="height:38px;width:38px;object-fit:cover;">
                                @endif
                                <textarea name="character" maxlength="45" class="form-control" rows="1">{{ $character->name }}</textarea>
                                <input type="file" name="image" accept="image/*" class="form-control">
                                <input type="hidden" name="idcharacter" value="{{ $character->idcharacter }}">
                                <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
                                <button type="submit" name="action" value="Delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this character?');">Delete</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endforeach
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
