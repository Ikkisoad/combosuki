@php
    $roster = [
        ['id' => 0, 'name' => 'Robo-Fortune', 'checkboxId' => 'skullgirls-RoboFortune'],
        ['id' => 1, 'name' => 'Valentine', 'checkboxId' => 'skullgirls-Valentine'],
        ['id' => 2, 'name' => 'Cerebella', 'checkboxId' => 'skullgirls-Cerebella'],
        ['id' => 3, 'name' => 'Ms Fortune', 'checkboxId' => 'skullgirls-MsFortune'],
        ['id' => 4, 'name' => 'Painwheel', 'checkboxId' => 'skullgirls-Painwheel'],
        ['id' => 5, 'name' => 'Squigly', 'checkboxId' => 'skullgirls-Squigly'],
        ['id' => 6, 'name' => 'Filia', 'checkboxId' => 'skullgirls-Filia'],
        ['id' => 7, 'name' => 'Peacock', 'checkboxId' => 'skullgirls-Peacock'],
        ['id' => 8, 'name' => 'Parasoul', 'checkboxId' => 'skullgirls-Parasoul'],
        ['id' => 9, 'name' => 'Eliza', 'checkboxId' => 'skullgirls-Eliza'],
        ['id' => 10, 'name' => 'Double', 'checkboxId' => 'skullgirls-Double'],
        ['id' => 11, 'name' => 'Big Band', 'checkboxId' => 'skullgirls-BigBand'],
        ['id' => 12, 'name' => 'Fukua', 'checkboxId' => 'skullgirls-Fukua'],
        ['id' => 13, 'name' => 'Beowulf', 'checkboxId' => 'skullgirls-Beowulf'],
        ['id' => 14, 'name' => 'Annie', 'checkboxId' => 'skullgirls-Annie'],
        ['id' => 15, 'name' => 'Umbrella', 'checkboxId' => 'skullgirls-Umbrella'],
        ['id' => 16, 'name' => 'Black Dahlia', 'checkboxId' => 'skullgirls-BlackDahlia'],
        ['id' => 17, 'name' => 'Marie', 'checkboxId' => 'skullgirls-Marie'],
    ];
    $sortedRoster = collect($roster)->sortBy('name')->values();
@endphp
<x-layouts.app :title="'Skullgirls Randomizer - Combo好き'" :description="'Randomize a Skullgirls team, moves, and colors.'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <p><a href="{{ route('randomizer.index') }}" class="link-light">&larr; Randomizers</a></p>
        <h2>Skullgirls Randomizer</h2>

        <div class="card combosuki-main-reversed text-white p-3 mb-3">
            <h5>Roster</h5>
            <p class="small text-white-50">Uncheck a character to exclude them from randomization.</p>
            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-6 g-2">
                @foreach ($roster as $character)
                    <div class="col form-check">
                        <input type="checkbox" class="form-check-input" id="{{ $character['checkboxId'] }}" checked>
                        <label class="form-check-label" for="{{ $character['checkboxId'] }}">{{ $character['name'] }}</label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card combosuki-main-reversed text-white p-3 mb-3">
            <h5>Force a Slot</h5>
            <div class="row row-cols-1 row-cols-sm-3 g-2">
                @for ($slot = 0; $slot < 3; $slot++)
                    <div class="col">
                        <select id="skullgirls-select-{{ $slot }}" class="form-select">
                            <option value="-1" selected>Random</option>
                            @foreach ($sortedRoster as $character)
                                <option value="{{ $character['id'] }}">{{ $character['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endfor
            </div>
        </div>

        <button id="skullgirls-new-team" type="button" class="btn btn-combosuki mb-3">New Team</button>
        <p id="skullgirls-message" class="text-warning"></p>

        <div class="card combosuki-main-reversed text-white p-3">
            <div class="row g-3 text-center">
                @for ($slot = 0; $slot < 3; $slot++)
                    <div class="col-4">
                        <img id="skullgirls-portrait-{{ $slot }}" src="" class="img-fluid rounded" alt="" style="max-width:100px">
                        <div id="skullgirls-name-{{ $slot }}" class="small mt-2"></div>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    @vite(['resources/js/randomizer.js'])
</x-layouts.app>
