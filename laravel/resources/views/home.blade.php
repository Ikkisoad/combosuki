<x-layouts.app>
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <div class="card combosuki-main-reversed text-white p-3 mb-3">
            <h2 class="mt-0">Challenge</h2>
            <x-daily-challenge :challenge="$challenge" />
        </div>

        <div class="body">
            <div class="row">
                @foreach ($games as $game)
                    <x-game-card :game="$game" />
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
