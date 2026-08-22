<x-layouts.app>
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <ul class="nav nav-tabs" id="home-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="challenge-tab" data-bs-toggle="tab" data-bs-target="#challenge-pane" type="button" role="tab" aria-controls="challenge-pane" aria-selected="true">Challenge</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="games-tab" data-bs-toggle="tab" data-bs-target="#games-pane" type="button" role="tab" aria-controls="games-pane" aria-selected="false">Games</button>
            </li>
        </ul>

        <div class="tab-content combosuki-main-reversed text-white p-3 border border-top-0" id="home-tabs-content">
            <div class="tab-pane fade show active" id="challenge-pane" role="tabpanel" aria-labelledby="challenge-tab">
                <x-daily-challenge :challenge="$challenge" />
            </div>
            <div class="tab-pane fade" id="games-pane" role="tabpanel" aria-labelledby="games-tab">
                <div class="row">
                    @foreach ($games as $game)
                        <x-game-card :game="$game" />
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
