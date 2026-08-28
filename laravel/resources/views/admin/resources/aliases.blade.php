<x-layouts.app :title="'Character Aliases for '.$resource->text_name.' - '.$game->name">
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

        <a href="{{ route('admin.resources.values', [$game, $resource]) }}" class="btn btn-secondary mb-3">&laquo; Back to Values</a>

        <p class="text-white">
            Leave a character's alias blank to fall back to the default value ({{ $values->pluck('value')->implode(', ') }}) and its default icon.
        </p>

        <form method="post" action="{{ route('admin.resources.aliases.store', [$game, $resource]) }}" enctype="multipart/form-data">
            @csrf

            <div class="table-responsive">
                <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                    <tr>
                        <th>Character</th>
                        @foreach ($values as $value)
                            <th>
                                <x-resource-value-icon :value="$value" />
                                {{ $value->value }}
                            </th>
                        @endforeach
                    </tr>
                    @foreach ($characters as $character)
                        <tr>
                            <td>{{ $character->name }}</td>
                            @foreach ($values as $value)
                                @php
                                    $current = $aliases->get($character->idcharacter.'-'.$value->idResources_values)?->first();
                                @endphp
                                <td>
                                    <div class="input-group">
                                        @if ($current?->icon)
                                            <span class="input-group-text p-1">
                                                <img src="{{ \Illuminate\Support\Facades\Storage::url($current->icon) }}" alt="{{ $current->alias }}" style="height: 20px; width: 20px; object-fit: contain;">
                                            </span>
                                        @endif
                                        <input type="text" name="aliases[{{ $character->idcharacter }}][{{ $value->idResources_values }}][alias]" maxlength="45" class="form-control" placeholder="{{ $value->value }}" value="{{ old("aliases.{$character->idcharacter}.{$value->idResources_values}.alias", $current?->alias) }}">
                                        <input type="file" name="aliases[{{ $character->idcharacter }}][{{ $value->idResources_values }}][icon]" accept="image/*" class="form-control" title="Icon (optional)">
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            </div>

            <button type="submit" class="btn btn-primary">Save all</button>
        </form>

        <x-admin.edit-nav :game="$game" />
    </div>
</x-layouts.app>
