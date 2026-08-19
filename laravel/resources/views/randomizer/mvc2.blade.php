<x-layouts.app :title="'MvC2 Randomizer - Combo好き'" :description="'Randomize a Marvel vs Capcom 2 team, assists, and colors.'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <p><a href="{{ route('randomizer.index') }}" class="link-light">&larr; Randomizers</a></p>
        <h2>Marvel vs Capcom 2 Randomizer</h2>
        <p class="small text-white-50">Ratio table by J.Wong</p>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <button id="mvc2-new-team" type="button" class="btn btn-combosuki">New Team</button>
            <button id="mvc2-ratio-team" type="button" class="btn btn-combosuki">Ratio Team</button>
            <label for="mvc2-ratio-max" class="ms-2 mb-0">Ratio max</label>
            <input type="number" id="mvc2-ratio-max" value="7" class="form-control" style="width:80px">
        </div>

        <div class="card combosuki-main-reversed text-white p-3">
            <h5 id="mvc2-title" class="mb-1"></h5>
            <p id="mvc2-ratios" class="small text-white-50"></p>
            <div class="row g-3 text-center">
                @for ($slot = 0; $slot < 3; $slot++)
                    <div class="col-4">
                        <img id="mvc2-portrait-{{ $slot }}" src="" class="img-fluid rounded" alt="">
                        <div id="mvc2-name-{{ $slot }}" class="mt-2"></div>
                        <div id="mvc2-color-{{ $slot }}" class="small text-white-50"></div>
                    </div>
                @endfor
            </div>
        </div>

        <p class="small text-white-50 mt-3">Randomizer by @Ikkisoad</p>
    </div>

    @vite(['resources/js/randomizer.js'])
</x-layouts.app>
