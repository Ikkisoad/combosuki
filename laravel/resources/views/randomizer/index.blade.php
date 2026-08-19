<x-layouts.app :title="'Randomizers - Combo好き'" :description="'Random team, character, and color generators for various fighting games.'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <h2>Randomizers</h2>
        <p class="text-white-50">Pick a game to generate a random team, character, and color.</p>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-3">
            <div class="col">
                <a href="{{ route('randomizer.dbfz') }}" class="btn btn-combosuki w-100 h-100 py-4">Dragon Ball FighterZ</a>
            </div>
            <div class="col">
                <a href="{{ route('randomizer.mvc2') }}" class="btn btn-combosuki w-100 h-100 py-4">Marvel vs Capcom 2</a>
            </div>
            <div class="col">
                <a href="{{ route('randomizer.skullgirls') }}" class="btn btn-combosuki w-100 h-100 py-4">Skullgirls</a>
            </div>
            <div class="col">
                <a href="{{ route('randomizer.dengeki') }}" class="btn btn-combosuki w-100 h-100 py-4">Dengeki Bunko: Fighting Climax</a>
            </div>
        </div>
    </div>
</x-layouts.app>
