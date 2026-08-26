<div id="comble-game-state">
    <p class="text-white-50">
        Guess the game, character and type behind {{ $isToday ? "today's" : "this day's" }} mystery combo.
        @unless ($finished)
            {{ $remaining }} {{ $remaining === 1 ? 'guess' : 'guesses' }} left.
        @endunless
    </p>

    <div class="card combosuki-main-reversed text-white p-3 mb-3">
        <div class="fs-4 text-center" style="letter-spacing: 2px;">
            <x-comble-reveal :game="$game" :notation="$target->combo" :guesses-made="count($guesses)" :finished="$finished" />
        </div>
    </div>

    @if (count($guesses) > 0)
        <table class="table table-hover align-middle combosuki-main-reversed text-white mb-3">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Game</th>
                    <th>Character</th>
                    <th>Type</th>
                    <th>Starter</th>
                    <th>Damage</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($guesses as $index => $guess)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="{{ $guess['game_correct'] ? 'bg-success' : 'bg-danger' }}">{{ $guess['game']->name }}</td>
                        <td class="{{ $guess['character_correct'] ? 'bg-success' : 'bg-danger' }}">{{ $guess['character']->name }}</td>
                        <td class="{{ $guess['type_correct'] ? 'bg-success' : 'bg-danger' }}">{{ $guess['listing_type']->title }}</td>
                        <td
                            class="{{ match ($guess['starter_result']) { 'correct' => 'bg-success', 'partial' => '', default => 'bg-danger' } }}"
                            style="{{ $guess['starter_result'] === 'partial' ? 'background-color: #fd7e14;' : '' }}"
                        >{{ $guess['starter'] ?: '—' }}</td>
                        <td>
                            {{ $guess['damage'] !== null ? number_format($guess['damage'], 0, '', '.') : '—' }}
                            @switch($guess['damage_hint'])
                                @case('higher')
                                    &uarr; Higher
                                    @break
                                @case('lower')
                                    &darr; Lower
                                    @break
                                @case('equal')
                                    &check; Equal
                                    @break
                                @default
                                    &mdash; Unknown
                            @endswitch
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($finished)
        <div class="card combosuki-main-reversed text-white p-3 mb-3">
            <h4>{{ $won ? 'You got it!' : 'Better luck tomorrow!' }}</h4>
            <p class="mb-2">
                {{ $target->character->name }} &mdash; {{ $game->name }}
                ({{ $target->listingType?->title }})
                @if ($target->damage !== null)
                    &middot; {{ number_format($target->damage, 0, '', '.') }} dmg
                @endif
            </p>

            <x-video-embed :video="$target->video" />

            <div class="mt-2 d-flex align-items-center gap-2">
                <a href="{{ route('combos.show', $target) }}" class="btn btn-dark">View this combo</a>
                <button type="button" id="comble-share-btn" class="btn btn-secondary" data-share-text="{{ $shareText }}">Copy Results</button>
                <span id="comble-share-feedback" class="small" style="display: none;"></span>
            </div>
        </div>
    @else
        <div id="comble-guess-error" class="alert alert-danger" style="display: none;"></div>

        <form method="post" action="{{ $isToday ? route('comble.guess') : route('comble.guess.date', ['date' => $day->toDateString()]) }}" id="comble-guess-form">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Game</label>
                    <select name="game_id" id="comble-game" class="form-select" required data-sticky="{{ $stickyGameId }}">
                        <option value="">Choose a game&hellip;</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Character</label>
                    <select name="character_id" id="comble-character" class="form-select" required disabled data-sticky="{{ $stickyCharacterId }}">
                        <option value="">Choose a game first&hellip;</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="listing_type_id" id="comble-type" class="form-select" required disabled data-sticky="{{ $stickyTypeId }}">
                        <option value="">Choose a game first&hellip;</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Starter <span class="text-white-50">(optional)</span></label>
                    <input type="text" name="starter" id="comble-starter" class="form-control" maxlength="6" placeholder="First 6 chars" value="{{ $stickyStarter }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Damage</label>
                    <input type="number" name="damage" id="comble-damage" class="form-control" min="0" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Guess</button>
                </div>
            </div>
        </form>
    @endif

    <div class="card combosuki-main-reversed text-white p-3 mb-3">
        <h4>Comble Stats {{ $isToday ? '— Today' : '— '.$day->format('M j, Y') }}</h4>
        <p class="mb-2 text-white-50">
            {{ number_format($stats['totalAttempts']) }} {{ \Illuminate\Support\Str::plural('play', $stats['totalAttempts']) }}
            &middot; {{ $stats['winRate'] }}% win rate
            &middot; {{ number_format($stats['totalPerfect']) }} perfect {{ \Illuminate\Support\Str::plural('score', $stats['totalPerfect']) }}
        </p>

        @if ($stats['totalAttempts'] > 0)
            @php $max = max($stats['distribution']); @endphp
            @foreach ([1, 2, 3, 4, 5, 'lost'] as $bucket)
                @php $count = $stats['distribution'][$bucket]; @endphp
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div style="width: 20px;" class="text-end small">{{ $bucket === 'lost' ? 'X' : $bucket }}</div>
                    <div class="flex-grow-1 bg-dark rounded">
                        <div
                            class="{{ $bucket === 'lost' ? 'bg-danger' : 'bg-success' }} rounded d-flex align-items-center justify-content-end px-2 text-dark small fw-bold"
                            style="height: 18px; width: {{ $count > 0 ? max(6, round($count / $max * 100)) : 0 }}%;"
                        >
                            @if ($count > 0)
                                {{ $count }}
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
