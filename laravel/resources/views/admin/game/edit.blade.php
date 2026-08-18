<x-layouts.app :title="'Edit Game Settings - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form method="post" action="{{ route('admin.game.update', $game) }}">
            @csrf
            <div class="input-group mb-3">
                <span class="input-group-text">Title:</span>
                <input type="text" name="title" class="form-control" value="{{ $game->name }}">
            </div>
            <div class="input-group mb-3">
                <span class="input-group-text">Current Version:</span>
                <input type="text" maxlength="10" name="patch" class="form-control" value="{{ $game->patch }}">
            </div>
            <div class="input-group mb-3">
                <span class="input-group-text">Image:</span>
                <input type="text" name="image" class="form-control" value="{{ $game->image }}">
            </div>
            @if ($game->image)
                <img src="{{ $game->image }}" height="250" class="mb-3">
            @endif
            <div class="input-group mb-3">
                <span class="input-group-text">Description:</span>
                <textarea name="description" class="form-control" rows="1" maxlength="255" placeholder="255 bytes brief description of the game page.">{{ $game->description }}</textarea>
            </div>
            <div class="input-group mb-3">
                <span class="input-group-text">Notation Guidelines:</span>
                <textarea name="notation" class="form-control" rows="1" maxlength="950" placeholder="1000 bytes guideline about combo notation.">{{ $game->notation }}</textarea>
            </div>
            <div class="btn-group">
                <button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Update</button>
                <button type="submit" name="action" value="Delete" class="btn btn-danger btn-block" onclick="return confirm('Are you sure you want to delete this game?');">Delete</button>
                @if (in_array($game->complete, [2, -1], true))
                    <button type="submit" name="action" value="Unlock" class="btn btn-secondary">Unlock</button>
                @else
                    <button type="submit" name="action" value="Lock" class="btn btn-secondary">Lock</button>
                @endif
            </div>
        </form>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
