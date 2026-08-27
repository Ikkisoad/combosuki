<x-layouts.app :title="'Unverified Combos - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <table class="table table-hover align-middle combosuki-main-reversed text-white">
            <tr>
                <th>Character</th>
                <th>Combo</th>
                <th>Author</th>
                <th>Submitted</th>
                <th></th>
            </tr>
            @forelse ($combos as $combo)
                <tr>
                    <td>{{ $combo->character->name }}</td>
                    <td><x-combo-link :combo="$combo" /></td>
                    <td>{{ $combo->user?->nickname ?? 'Anonymous' }}</td>
                    <td>{{ $combo->submited?->format('d-m-Y') }}</td>
                    <td>
                        @can('verify', $combo)
                            <form method="post" action="{{ route('combos.verify', $combo) }}"
                                  onsubmit="return confirm('Verify this combo?');" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">Verify</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No unverified combos.</td>
                </tr>
            @endforelse
        </table>

        {{ $combos->links() }}

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
