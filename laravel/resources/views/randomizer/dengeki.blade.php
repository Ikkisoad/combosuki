<x-layouts.app :title="'Dengeki Bunko Randomizer - Combo好き'" :description="'Randomize a Dengeki Bunko: Fighting Climax character, assist, and colors.'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <p><a href="{{ route('randomizer.index') }}" class="link-light">&larr; Randomizers</a></p>
        <h2>Dengeki Bunko: Fighting Climax Randomizer</h2>

        <button id="dengeki-new-team" type="button" class="btn btn-combosuki mb-3">New Team</button>

        <div class="card combosuki-main-reversed text-white p-3">
            <h5 id="dengeki-name" class="mb-3"></h5>
            <div class="row g-3 text-center">
                @for ($slot = 0; $slot < 2; $slot++)
                    <div class="col-6">
                        <img id="dengeki-portrait-{{ $slot }}" src="" class="img-fluid rounded" alt="">
                        <div id="dengeki-color-{{ $slot }}" class="small text-white-50 mt-2"></div>
                    </div>
                @endfor
            </div>
        </div>

        <p class="small text-white-50 mt-3">Randomizer by @Dominomorc, feel free to DM for any issues</p>
    </div>

    @vite(['resources/js/randomizer.js'])
</x-layouts.app>
