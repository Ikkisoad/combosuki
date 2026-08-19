<x-layouts.app :title="'DBFZ Randomizer - Combo好き'" :description="'Randomize a Dragon Ball FighterZ team, assists, and colors.'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <p><a href="{{ route('randomizer.index') }}" class="link-light">&larr; Randomizers</a></p>
        <h2>Dragon Ball FighterZ Randomizer</h2>

        <button id="dbfz-new-team" type="button" class="btn btn-combosuki mb-3">New Team</button>

        <div class="card combosuki-main-reversed text-white p-3">
            <div class="row g-3 text-center">
                @for ($slot = 0; $slot < 3; $slot++)
                    <div class="col-4">
                        <img id="dbfz-portrait-{{ $slot }}" src="" class="img-fluid rounded" alt="">
                        <div id="dbfz-name-{{ $slot }}" class="mt-2"></div>
                        <div id="dbfz-color-{{ $slot }}" class="small text-white-50"></div>
                    </div>
                @endfor
            </div>
        </div>

        <p class="small text-white-50 mt-3">Randomizer by @Ikkisoad</p>
    </div>

    @vite(['resources/js/randomizer.js'])
</x-layouts.app>
