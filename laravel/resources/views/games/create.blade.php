<x-layouts.app :title="'Add Game - Combo好き'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2>Add Game</h2>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('games.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Game Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" maxlength="100" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Game Image URL</label>
                        <input type="text" name="image" class="form-control" value="{{ old('image') }}" maxlength="255" required>
                    </div>
                    <div class="mb-2">
                        <button type="submit" class="btn btn-combosuki">Submit</button>
                    </div>
                </form>

                <p class="mt-3">This creates the game with a default character, buttons, resource and combo types. You'll be taken to the game's settings to fill in the rest.</p>
            </div>
        </div>
    </div>
</x-layouts.app>
