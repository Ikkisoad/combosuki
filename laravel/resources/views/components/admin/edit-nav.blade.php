@props(['game'])

<div class="btn-group mb-3">
    <a href="{{ route('admin.characters.index', $game) }}" class="btn btn-secondary">Characters</a>
    <a href="{{ route('admin.buttons.index', $game) }}" class="btn btn-secondary">Buttons</a>
    <a href="{{ route('admin.resources.index', $game) }}" class="btn btn-secondary">Resources</a>
    <a href="{{ route('admin.queries.index', $game) }}" class="btn btn-secondary">Queries</a>
    <a href="{{ route('admin.links.index', $game) }}" class="btn btn-secondary">Links</a>
    <a href="{{ route('admin.entries.index', $game) }}" class="btn btn-secondary">Entries</a>
    <a href="{{ route('admin.lists.index', $game) }}" class="btn btn-secondary">Guides</a>
    <a href="{{ route('admin.unverified-combos.index', $game) }}" class="btn btn-secondary">Unverified Combos</a>
    <a href="{{ route('admin.game.edit', $game) }}" class="btn btn-secondary">Game Settings</a>
</div>
