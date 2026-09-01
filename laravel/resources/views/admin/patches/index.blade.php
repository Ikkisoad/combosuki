<x-layouts.app :title="'Edit Patches - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <tr><th>Label</th><th>Start Date</th><th>End Date</th><th></th></tr>
            @foreach ($patches as $patch)
                <tr>
                    <td>
                        <form method="post" action="{{ route('admin.patches.store', $game) }}">
                            @csrf
                            <div class="input-group">
                                <input type="text" name="label" maxlength="10" class="form-control" value="{{ $patch->label }}">
                                <input type="date" name="released_at" class="form-control" value="{{ $patch->released_at->toDateString() }}" @disabled(! $patch->isCurrent())>
                                <span class="input-group-text">{{ $patch->isCurrent() ? 'current' : $patch->ended_at->format('M j, Y') }}</span>
                                <input type="hidden" name="idgame_patch" value="{{ $patch->idgame_patch }}">
                                <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
                                <button type="submit" name="action" value="Delete" class="btn btn-danger" data-confirm="Are you sure you want to delete this patch? Its date range and any combos will be folded into the neighboring patch.">Delete</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td>
                    <form method="post" action="{{ route('admin.patches.store', $game) }}">
                        @csrf
                        <div class="input-group">
                            <input type="text" name="label" maxlength="10" class="form-control" placeholder="Label, e.g. 1.04" autofocus>
                            <input type="date" name="released_at" class="form-control" value="{{ now()->toDateString() }}">
                            <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                </td>
            </tr>
        </table>
        <p>Adding a new patch closes out the current one, using the new patch's start date as the previous patch's end date. Only the current patch's start date can be edited; any patch's label can be renamed. Deleting a patch folds its date range (and any combos on it) into the previous patch if one exists, otherwise into the next patch; deleting the current patch reopens the previous one.</p>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
