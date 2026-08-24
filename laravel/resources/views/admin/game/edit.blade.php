<x-layouts.app :title="'Edit Game Settings - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form method="post" action="{{ route('admin.game.update', $game) }}" enctype="multipart/form-data">
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
                <span class="input-group-text">Logo:</span>
                <input type="file" name="image" accept="image/*" class="form-control">
            </div>
            @if ($game->logo_url)
                <img src="{{ $game->logo_url }}" height="250" class="mb-3">
            @endif
            <div class="input-group mb-3">
                <span class="input-group-text">Description:</span>
                <textarea name="description" class="form-control" rows="1" maxlength="255" placeholder="255 bytes brief description of the game page.">{{ $game->description }}</textarea>
            </div>
            <div class="input-group mb-3">
                <span class="input-group-text">Notation Guidelines:</span>
                <textarea name="notation" class="form-control" rows="1" maxlength="950" placeholder="1000 bytes guideline about combo notation.">{{ $game->notation }}</textarea>
            </div>
            <div class="input-group mb-3">
                <span class="input-group-text">Aliases:</span>
                <input type="text" name="aliases" class="form-control" maxlength="1000" value="{{ old('aliases', $game->aliases->pluck('alias')->implode(', ')) }}" placeholder="Comma-separated, e.g. SF6, Street Fighter 6">
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="matches_enabled" id="matches_enabled" class="form-check-input" value="1" @checked(old('matches_enabled', $game->matches_enabled))>
                <label class="form-check-label" for="matches_enabled">Enable Matches feature</label>
            </div>
            <div class="input-group mb-3">
                <span class="input-group-text">External Matches Database Link:</span>
                <input type="text" name="matches_url" class="form-control" maxlength="255" value="{{ old('matches_url', $game->matches_url) }}" placeholder="If matches for this game are already tracked elsewhere, link it here.">
            </div>
            <div class="btn-group">
                <button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Update</button>
                <button type="submit" name="action" value="Delete" class="btn btn-danger btn-block" onclick="return confirm('Are you sure you want to delete this game?');">Delete</button>
                @if ($game->isLocked())
                    <button type="submit" name="action" value="Unlock" class="btn btn-secondary">Unlock</button>
                @else
                    <button type="submit" name="action" value="Lock" class="btn btn-secondary">Lock</button>
                @endif
                @if (auth()->user()->is_admin)
                    @if ($game->isComplete())
                        <button type="submit" name="action" value="Incomplete" class="btn btn-secondary">Mark Incomplete</button>
                    @else
                        <button type="submit" name="action" value="Complete" class="btn btn-secondary">Mark Complete</button>
                    @endif
                @endif
            </div>
        </form>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
