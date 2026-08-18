<x-layouts.app :title="'Games - Combo好き'">
    <x-jumbotron :height="100" />
    <x-nav-bar />

    <div class="container my-3">
        <h2>Games</h2>
        <div class="row">
            @foreach ($games as $game)
                <x-game-card :game="$game" />
            @endforeach
        </div>
    </div>
</x-layouts.app>
