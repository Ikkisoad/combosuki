<x-layouts.app>
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <x-donation-bar :hide-when-met="true" />

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

            <div class="text-center my-3">
                <a href="{{ route('games.index') }}" class="btn btn-combosuki text-white">Check out all games</a>
            </div>
        </div>
    </div>
</x-layouts.app>
