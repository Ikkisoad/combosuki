@props(['list'])

<div class="btn-group mb-3">
    <a href="{{ route('lists.show', $list) }}" class="btn btn-secondary">&larr; Back to List</a>
    <a href="#settings" class="btn btn-secondary">Settings</a>
    <a href="#pages" class="btn btn-secondary">Pages</a>
    <a href="#categories" class="btn btn-secondary">Categories</a>
    <a href="#combos" class="btn btn-secondary">Combos</a>
    <a href="{{ route('lists.manage.combos.index', $list) }}" class="btn btn-primary">Add Combos</a>
</div>
