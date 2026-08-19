<x-layouts.app :title="'Edit Buttons - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form id="bulk-buttons-form" method="post" action="{{ route('admin.buttons.bulkUpdate', $game) }}">
            @csrf
        </form>

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <caption>Edit the fields below and click "Save All" to update every button at once.</caption>
            <tr><th>Button</th></tr>
            @foreach ($buttons as $button)
                <tr>
                    <td>
                        <div class="input-group">
                            <textarea form="bulk-buttons-form" name="buttons[{{ $button->idbutton }}][name]" maxlength="45" class="form-control" rows="1">{{ $button->name }}</textarea>
                            <select form="bulk-buttons-form" name="buttons[{{ $button->idbutton }}][match_type]" class="form-select">
                                @foreach (['contains', 'starts_with', 'ends_with', 'exact'] as $type)
                                    <option value="{{ $type }}" @selected($type === $button->match_type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            <input form="bulk-buttons-form" type="color" name="buttons[{{ $button->idbutton }}][color]" class="form-control form-control-color" value="{{ $button->color }}">
                            <input form="bulk-buttons-form" class="form-control" type="number" name="buttons[{{ $button->idbutton }}][order]" value="{{ $button->order }}" step="any">
                            <form method="post" action="{{ route('admin.buttons.store', $game) }}">
                                @csrf
                                <input type="hidden" name="idbutton" value="{{ $button->idbutton }}">
                                <button type="submit" name="action" value="Delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this button?');">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td>
                    <button type="submit" form="bulk-buttons-form" class="btn btn-primary">Save All</button>
                </td>
            </tr>
            <tr>
                <td>
                    <form method="post" action="{{ route('admin.buttons.store', $game) }}">
                        @csrf
                        <div class="input-group">
                            <textarea name="name" maxlength="45" class="form-control" rows="1" placeholder="Button Name" autofocus></textarea>
                            <select name="match_type" class="form-select">
                                @foreach (['contains', 'starts_with', 'ends_with', 'exact'] as $type)
                                    <option value="{{ $type }}" @selected($type === 'exact')>{{ $type }}</option>
                                @endforeach
                            </select>
                            <input type="color" name="color" class="form-control form-control-color" value="#ffffff">
                            <input class="form-control" type="number" name="order" step="any">
                            <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                </td>
            </tr>
        </table>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
