<x-layouts.app :title="'Edit Button Aliases - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form id="bulk-button-aliases-form" method="post" action="{{ route('admin.button-aliases.bulkUpdate', $game) }}">
            @csrf
        </form>

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <caption>
                Make a word (e.g. "Throw") searchable as another name for one of this game's existing buttons (e.g.
                "LP+LK") — searching either one will find combos written with the other. Edit the fields below and
                click "Save All" to update every alias at once.
            </caption>
            <tr><th>Alias</th><th>Button</th></tr>
            @foreach ($buttonAliases as $buttonAlias)
                <tr>
                    <td>
                        <div class="input-group">
                            <input form="bulk-button-aliases-form" type="text" name="aliases[{{ $buttonAlias->idbuttonalias }}][alias]" maxlength="45" class="form-control" value="{{ $buttonAlias->alias }}">
                            <select form="bulk-button-aliases-form" name="aliases[{{ $buttonAlias->idbuttonalias }}][button_idbutton]" class="form-select">
                                @foreach ($buttons as $button)
                                    <option value="{{ $button->idbutton }}" @selected($button->idbutton === $buttonAlias->button_idbutton)>{{ $button->name }}</option>
                                @endforeach
                            </select>
                            <form method="post" action="{{ route('admin.button-aliases.store', $game) }}" data-confirm="Are you sure you want to delete this alias?">
                                @csrf
                                <input type="hidden" name="idbuttonalias" value="{{ $buttonAlias->idbuttonalias }}">
                                <button type="submit" name="action" value="Delete" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td>
                    <button type="submit" form="bulk-button-aliases-form" class="btn btn-primary">Save All</button>
                </td>
            </tr>
            <tr>
                <td>
                    <form method="post" action="{{ route('admin.button-aliases.store', $game) }}">
                        @csrf
                        <div class="input-group">
                            <input type="text" name="alias" maxlength="45" class="form-control" placeholder="Alias (e.g. Throw)" autofocus>
                            <select name="button_idbutton" class="form-select">
                                @foreach ($buttons as $button)
                                    <option value="{{ $button->idbutton }}">{{ $button->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                    @if ($buttons->isEmpty())
                        <div class="form-text text-white">Add a button on the <a href="{{ route('admin.buttons.index', $game) }}">Buttons</a> page first — aliases can only point at a button that already exists.</div>
                    @endif
                </td>
            </tr>
        </table>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
