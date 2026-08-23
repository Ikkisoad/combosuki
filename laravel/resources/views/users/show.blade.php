<x-layouts.app :title="$user->nickname.' - Combo好き'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h2 class="text-white mb-0">
                {{ $user->nickname }}
                @if ($user->is_admin)
                    <span class="badge bg-danger align-middle">Admin</span>
                @elseif ($user->isTrusted())
                    <span class="badge bg-warning text-dark align-middle">Trusted</span>
                @endif
            </h2>
            @if ($isOwnProfile)
                <a href="{{ route('password.edit') }}" class="btn btn-combosuki">Change Password</a>
            @endif
        </div>

        <div class="row row-cols-2 row-cols-md-3 g-3 mb-3">
            <div class="col">
                <div class="card combosuki-main-reversed text-white p-3 h-100">
                    <div class="text-white-50 small text-uppercase">Combos Submitted</div>
                    <div class="fs-3 fw-bold">{{ number_format($stats['totalCombos']) }}</div>
                </div>
            </div>
            <div class="col">
                <div class="card combosuki-main-reversed text-white p-3 h-100">
                    <div class="text-white-50 small text-uppercase">Most Submitted Game</div>
                    @if ($stats['mostSubmittedGame'])
                        <a href="{{ route('games.show', $stats['mostSubmittedGame']['game']) }}" class="fs-5 fw-bold text-white">{{ $stats['mostSubmittedGame']['game']->name }}</a>
                        <div class="text-white-50 small">{{ number_format($stats['mostSubmittedGame']['count']) }} {{ \Illuminate\Support\Str::plural('combo', $stats['mostSubmittedGame']['count']) }}</div>
                    @else
                        <div class="fs-5">&mdash;</div>
                    @endif
                </div>
            </div>
            <div class="col">
                <div class="card combosuki-main-reversed text-white p-3 h-100">
                    <div class="text-white-50 small text-uppercase">Most Submitted Character</div>
                    @if ($stats['mostSubmittedCharacter'])
                        @php $character = $stats['mostSubmittedCharacter']['character']; @endphp
                        <a href="{{ route('characters.show', [$character->game, $character]) }}" class="fs-5 fw-bold text-white">{{ $character->name }}</a>
                        <div class="text-white-50 small">{{ number_format($stats['mostSubmittedCharacter']['count']) }} {{ \Illuminate\Support\Str::plural('combo', $stats['mostSubmittedCharacter']['count']) }}</div>
                    @else
                        <div class="fs-5">&mdash;</div>
                    @endif
                </div>
            </div>
            @if ($isOwnProfile)
                <div class="col">
                    <div class="card combosuki-main-reversed text-white p-3 h-100">
                        <div class="text-white-50 small text-uppercase">My Favorites</div>
                        @if ($user->favoriteGuide)
                            <a href="{{ route('lists.show', $user->favoriteGuide) }}" class="fs-5 fw-bold text-white">View Favorites &rarr;</a>
                        @else
                            <div class="text-white-50">You haven't favorited any combos yet.</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <h4 class="mb-2 text-white">Most Viewed Combos</h4>
        @if ($mostViewedCombos->isEmpty())
            <p class="text-white">No combos submitted yet.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                    <tr>
                        <th>Character</th>
                        <th>Inputs</th>
                        <th>Damage</th>
                        <th>Type</th>
                        <th>Submitted</th>
                    </tr>
                    @foreach ($mostViewedCombos as $combo)
                        <tr>
                            <td>{{ $combo->character->name }}</td>
                            <td style="min-width:400px">
                                <x-combo-link :combo="$combo" />
                            </td>
                            <td>{{ number_format((float) $combo->damage, 0, '', '.') }}</td>
                            <td>{{ $combo->listingType?->title }}</td>
                            <td>{{ $combo->submited?->format('d-m-y') }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
