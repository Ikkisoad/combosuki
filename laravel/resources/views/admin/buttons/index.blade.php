<x-layouts.app :title="'Edit Buttons - '.$game->name">
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <tr><th>Button</th></tr>
            @foreach ($buttons as $button)
                <tr>
                    <td>
                        <form method="post" action="{{ route('admin.buttons.store', $game) }}">
                            @csrf
                            <div class="input-group">
                                <textarea name="name" maxlength="45" class="form-control" rows="1">{{ $button->name }}</textarea>
                                <select name="png" class="form-select" onchange="setImage(this, {{ $button->idbutton }})">
                                    @foreach ($images as $image)
                                        <option value="{{ $image }}" @selected($image === $button->png)>{{ $image }}</option>
                                    @endforeach
                                </select>
                                <img src="{{ asset('img/buttons/'.$button->png.'.png') }}" height="35" name="image-{{ $button->idbutton }}">
                                <input class="form-control" type="number" name="order" value="{{ $button->order }}" step="any">
                                <input type="hidden" name="idbutton" value="{{ $button->idbutton }}">
                                <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
                                <button type="submit" name="action" value="Delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this button?');">Delete</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td>
                    <form method="post" action="{{ route('admin.buttons.store', $game) }}">
                        @csrf
                        <div class="input-group">
                            <textarea name="name" maxlength="45" class="form-control" rows="1" placeholder="Button Name" autofocus></textarea>
                            <select name="png" class="form-select" onchange="setImage(this, 0)">
                                @foreach ($images as $image)
                                    <option value="{{ $image }}">{{ $image }}</option>
                                @endforeach
                            </select>
                            <img src="{{ asset('img/buttons/+.png') }}" height="35" name="image-0">
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
