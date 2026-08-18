<x-layouts.app>
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <div class="body">
            <div class="row">
                @foreach ($games as $game)
                    <x-game-card :game="$game" />
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
