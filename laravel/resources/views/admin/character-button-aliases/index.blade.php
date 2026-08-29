<x-layouts.app :title="'Edit Character Move Aliases - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form id="bulk-character-button-aliases-form" method="post" action="{{ route('admin.character-button-aliases.bulkUpdate', $game) }}">
            @csrf
        </form>

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <caption>
                Make a word (e.g. "Tackle") searchable as another name for one of this game's existing buttons (e.g.
                "236A"), but only for one character — the same word is free to mean something else for every other
                character. Edit the fields below and click "Save All" to update every alias at once.
            </caption>
            <tr><th>Alias</th><th>Character</th><th>Button</th></tr>
            @foreach ($characterButtonAliases as $characterButtonAlias)
                <tr>
                    <td>
                        <div class="input-group">
                            <input form="bulk-character-button-aliases-form" type="text" name="aliases[{{ $characterButtonAlias->idcharacterbuttonalias }}][alias]" maxlength="45" class="form-control" value="{{ $characterButtonAlias->alias }}">
                            <select form="bulk-character-button-aliases-form" name="aliases[{{ $characterButtonAlias->idcharacterbuttonalias }}][character_idcharacter]" class="form-select">
                                @foreach ($characters as $character)
                                    <option value="{{ $character->idcharacter }}" @selected($character->idcharacter === $characterButtonAlias->character_idcharacter)>{{ $character->name }}</option>
                                @endforeach
                            </select>
                            <select form="bulk-character-button-aliases-form" name="aliases[{{ $characterButtonAlias->idcharacterbuttonalias }}][button_idbutton]" class="form-select">
                                @foreach ($buttons as $button)
                                    <option value="{{ $button->idbutton }}" @selected($button->idbutton === $characterButtonAlias->button_idbutton)>{{ $button->name }}</option>
                                @endforeach
                            </select>
                            <form method="post" action="{{ route('admin.character-button-aliases.store', $game) }}">
                                @csrf
                                <input type="hidden" name="idcharacterbuttonalias" value="{{ $characterButtonAlias->idcharacterbuttonalias }}">
                                <button type="submit" name="action" value="Delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this alias?');">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td>
                    <button type="submit" form="bulk-character-button-aliases-form" class="btn btn-primary">Save All</button>
                </td>
            </tr>
            <tr>
                <td>
                    <form method="post" action="{{ route('admin.character-button-aliases.store', $game) }}">
                        @csrf
                        <div class="input-group">
                            <input type="text" name="alias" maxlength="45" class="form-control" placeholder="Alias (e.g. Tackle)" autofocus>
                            <select name="character_idcharacter" class="form-select">
                                @foreach ($characters as $character)
                                    <option value="{{ $character->idcharacter }}">{{ $character->name }}</option>
                                @endforeach
                            </select>
                            <select name="button_idbutton" class="form-select">
                                @foreach ($buttons as $button)
                                    <option value="{{ $button->idbutton }}">{{ $button->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                    @if ($characters->isEmpty())
                        <div class="form-text text-white">Add a character on the <a href="{{ route('admin.characters.index', $game) }}">Characters</a> page first — aliases can only point at a character that already exists.</div>
                    @elseif ($buttons->isEmpty())
                        <div class="form-text text-white">Add a button on the <a href="{{ route('admin.buttons.index', $game) }}">Buttons</a> page first — aliases can only point at a button that already exists.</div>
                    @endif
                </td>
            </tr>
        </table>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
