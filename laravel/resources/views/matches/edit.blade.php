<x-layouts.app :title="'Edit Match - '.$game->name">
    <x-jumbotron :height="150" />
    <x-nav-bar :game="$game" />

    <div class="container my-3">
        <h2>Edit match</h2>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('matches.update', $match) }}">
            @csrf

            <div class="row">
                <x-matches.player-fields
                    label="Player 1"
                    name-field="player_one"
                    user-field="player_one_user_iduser"
                    character-field="player_one_character_idcharacter"
                    :characters="$characters"
                    :name-value="$match->player_one"
                    :user-value="$match->player_one_user_iduser"
                    :user-label="$match->playerOneUser?->nickname"
                    :character-value="$match->player_one_character_idcharacter"
                />
                <x-matches.player-fields
                    label="Player 2"
                    name-field="player_two"
                    user-field="player_two_user_iduser"
                    character-field="player_two_character_idcharacter"
                    :characters="$characters"
                    :name-value="$match->player_two"
                    :user-value="$match->player_two_user_iduser"
                    :user-label="$match->playerTwoUser?->nickname"
                    :character-value="$match->player_two_character_idcharacter"
                />
            </div>

            <div class="input-group mb-3">
                <span class="input-group-text">Date Played:</span>
                <input type="date" name="played_at" class="form-control" required value="{{ old('played_at', $match->played_at->format('Y-m-d')) }}">
            </div>

            <label>Video:</label>
            <textarea name="video" class="form-control" rows="1" maxlength="255" required
                      placeholder="Currently supports YouTube, Twitter/X, Streamable, Twitch clips, Imgur, Niconico, Gfycat and MedalTv.">{{ old('video', $match->video) }}</textarea>

            <div class="row">
                <div class="col my-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="{{ route('games.matches.index', $game) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>

        <form method="post" action="{{ route('matches.destroy', $match) }}" onsubmit="return confirm('Are you sure you want to delete this match?');">
            @csrf
            <button type="submit" class="btn btn-danger">Delete Match</button>
        </form>
    </div>
</x-layouts.app>
