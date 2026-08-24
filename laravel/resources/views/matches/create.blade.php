<x-layouts.app :title="'Submit a Match - '.$game->name">
    <x-jumbotron :height="150" />
    <x-nav-bar :game="$game" />

    <div class="container my-3">
        <h2>Submit a match</h2>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('games.matches.store', $game) }}">
            @csrf

            <div class="row">
                <x-matches.player-fields
                    label="Player 1"
                    name-field="player_one"
                    user-field="player_one_user_iduser"
                    character-field="player_one_character_idcharacter"
                    :characters="$characters"
                    resources-field="player_one_resources"
                    :resources="$matchResources"
                />
                <x-matches.player-fields
                    label="Player 2"
                    name-field="player_two"
                    user-field="player_two_user_iduser"
                    character-field="player_two_character_idcharacter"
                    :characters="$characters"
                    resources-field="player_two_resources"
                    :resources="$matchResources"
                />
            </div>

            <div class="input-group mb-3">
                <span class="input-group-text">Date Played:</span>
                <input type="date" name="played_at" class="form-control" required value="{{ old('played_at') }}">
            </div>

            <label>Video:</label>
            <textarea name="video" class="form-control" rows="1" maxlength="255" required
                      placeholder="Currently supports YouTube, Twitter/X, Streamable, Twitch clips, Imgur, Niconico, Gfycat and MedalTv.">{{ old('video') }}</textarea>

            <div class="row">
                <div class="col my-3">
                    <button type="submit" class="btn btn-primary btn-block">Submit</button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.app>
