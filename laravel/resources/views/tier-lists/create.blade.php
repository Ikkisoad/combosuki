<x-layouts.app :title="'Tier List Maker - Combo好き'" description="Sort a game's characters into S, A, B, C, D and F tiers and share the result.">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container-fluid my-3">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2>Tier List Maker</h2>
            <a href="{{ route('tier-lists.index') }}" class="btn btn-sm btn-outline-light">Browse tier lists</a>
        </div>
        <p class="text-white-50">Pick a game, drag its characters into a tier, then title and submit your list. Characters left in Unranked won't be saved.</p>
        <p class="text-warning"><strong>Heads up:</strong> once submitted, a tier list cannot be edited.</p>

        <form method="post" action="{{ route('tier-lists.store') }}" id="tier-list-form">
            @csrf

            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-5">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control" maxlength="100" required value="{{ old('title') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Game</label>
                    <select name="game_idgame" id="tier-list-game" class="form-select" required>
                        <option value="">Choose a game&hellip;</option>
                        @foreach ($games as $game)
                            <option value="{{ $game->idgame }}" @selected(old('game_idgame') == $game->idgame)>{{ $game->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Submit Tier List</button>
                </div>
            </div>

            @if (auth()->user()->is_admin)
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Created date <span class="text-white-50">(admin)</span></label>
                        <input type="date" name="created_at" class="form-control" value="{{ old('created_at') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Author <span class="text-white-50">(admin)</span></label>
                        <select name="user_iduser" class="form-select">
                            <option value="{{ auth()->id() }}" @selected(old('user_iduser', auth()->id()) == auth()->id())>Myself ({{ auth()->user()->nickname }})</option>
                            <option value="" @selected(old('user_iduser') === '')>Anonymous</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->iduser }}" @selected(old('user_iduser') == $user->iduser)>{{ $user->nickname }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

            <div id="tier-list-entries-fields"></div>

            <div id="tier-board" style="display:none;">
                @foreach (\App\Models\TierListEntry::TIERS as $tier)
                    <div class="tier-row d-flex align-items-stretch mb-2">
                        <div class="tier-label tier-{{ strtolower($tier) }} d-flex align-items-center justify-content-center fw-bold">{{ $tier }}</div>
                        <div class="tier-dropzone flex-grow-1 d-flex flex-wrap gap-2 p-2" data-tier="{{ $tier }}"></div>
                    </div>
                @endforeach
            </div>

            <div class="card combosuki-main-reversed text-white p-3 mt-3">
                <h5>Unranked</h5>
                <div id="unranked-pool" class="tier-dropzone d-flex flex-wrap gap-2 p-2" data-tier=""></div>
            </div>
        </form>
    </div>

    <script type="application/json" id="tier-list-catalog">{!! json_encode($catalog) !!}</script>
    @vite(['resources/js/tier-list-maker.js'])
</x-layouts.app>
