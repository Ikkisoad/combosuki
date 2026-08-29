<x-layouts.app :title="'Manage '.$list->list_name.' - Combo好き'">
    <x-jumbotron :height="100" />
    <x-nav-bar :game="$list->game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
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

        <h3>Manage &ldquo;{{ $list->list_name }}&rdquo;</h3>
        <p class="text-white-50">By {{ $list->user?->nickname ?? 'Anonymous' }}</p>

        <x-lists.manage-nav :list="$list" />

        <section id="settings" class="card combosuki-main-reversed text-white p-3 mb-4">
            <h4>Settings</h4>
            <form method="post" action="{{ route('lists.rename', $list) }}" class="d-flex gap-2 mb-2">
                @csrf
                <input type="text" name="list_name" maxlength="100" class="form-control" value="{{ $list->list_name }}">
                <button class="btn btn-primary text-nowrap">Rename</button>
            </form>
            <form method="post" action="{{ route('lists.destroy', $list) }}" class="d-flex gap-2" onsubmit="return confirm('Are you sure you want to delete this list? This also deletes all of its pages and categories.');">
                @csrf
                <button class="btn btn-danger text-nowrap">Delete List</button>
            </form>
        </section>

        <section id="pages" class="card combosuki-main-reversed text-white p-3 mb-4">
            <h4>Pages</h4>
            <p class="text-white-50">Predefine the pages your guide is split across. A page groups categories together and shows its own tab when viewing the list.</p>

            @foreach ($pages as $page)
                <form id="page-delete-{{ $page->idListPage }}" method="post" action="{{ route('lists.manage.pages.destroy', [$list, $page]) }}" onsubmit="return confirm('Delete this page? Its categories will become unassigned from any page.');">@csrf</form>
            @endforeach

            <div id="pages-table" data-list-id="{{ $list->idlist }}" data-save-url="{{ route('lists.manage.pages.bulk', $list) }}" class="table-responsive">
                <table class="table table-hover align-middle text-white">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th style="width:100px">Order</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $page)
                            <tr data-page-id="{{ $page->idListPage }}">
                                <td><input type="text" data-field="Title" maxlength="255" class="form-control form-control-sm" value="{{ $page->Title }}"></td>
                                <td><input type="text" data-field="Description" maxlength="1000" class="form-control form-control-sm" value="{{ $page->Description }}"></td>
                                <td><input type="number" data-field="order" class="form-control form-control-sm" value="{{ $page->order }}"></td>
                                <td class="text-nowrap">
                                    <button form="page-delete-{{ $page->idListPage }}" type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No pages yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($pages->isNotEmpty())
                <div class="d-flex align-items-center gap-2 mb-3">
                    <button type="button" id="save-pages-btn" class="btn btn-primary">Save All Pages</button>
                    <span id="save-pages-status" class="small"></span>
                </div>
            @endif

            <form method="post" action="{{ route('lists.manage.pages.store', $list) }}" class="row g-2 mt-2">
                @csrf
                <div class="col-auto">
                    <input type="text" name="Title" maxlength="255" placeholder="Title" class="form-control" required>
                </div>
                <div class="col-auto">
                    <input type="text" name="Description" maxlength="1000" placeholder="Description (optional)" class="form-control">
                </div>
                <div class="col-auto">
                    <input type="number" name="order" placeholder="Order" class="form-control" style="width:100px">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Add Page</button>
                </div>
            </form>
        </section>

        <section id="categories" class="card combosuki-main-reversed text-white p-3 mb-4">
            <h4>Categories</h4>
            <p class="text-white-50">Predefine categories and optionally assign each one to a page, then bulk-add combos into them from the combo picker.</p>

            @foreach ($categories as $category)
                <form id="category-delete-{{ $category->idlist_category }}" method="post" action="{{ route('lists.manage.categories.destroy', [$list, $category]) }}" onsubmit="return confirm('Delete this category? Its combos will become uncategorized, not removed from the list.');">@csrf</form>
            @endforeach

            <div id="categories-table" data-list-id="{{ $list->idlist }}" data-save-url="{{ route('lists.manage.categories.bulk', $list) }}" class="table-responsive">
                <table class="table table-hover align-middle text-white">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Page</th>
                            <th style="width:100px">Order</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr data-category-id="{{ $category->idlist_category }}">
                                <td><input type="text" data-field="title" maxlength="50" class="form-control form-control-sm" value="{{ $category->title }}"></td>
                                <td><input type="text" data-field="description" maxlength="1000" class="form-control form-control-sm" value="{{ $category->description }}"></td>
                                <td>
                                    <select data-field="idPage" class="form-select form-select-sm">
                                        <option value="">No Page</option>
                                        @foreach ($pages as $page)
                                            <option value="{{ $page->idListPage }}" @selected($category->idPage === $page->idListPage)>{{ $page->Title }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" data-field="order" class="form-control form-control-sm" value="{{ $category->order }}"></td>
                                <td class="text-nowrap">
                                    <button form="category-delete-{{ $category->idlist_category }}" type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No categories yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($categories->isNotEmpty())
                <div class="d-flex align-items-center gap-2 mb-3">
                    <button type="button" id="save-categories-btn" class="btn btn-primary">Save All Categories</button>
                    <span id="save-categories-status" class="small"></span>
                </div>
            @endif

            <form method="post" action="{{ route('lists.manage.categories.store', $list) }}" class="row g-2 mt-2">
                @csrf
                <div class="col-auto">
                    <input type="text" name="title" maxlength="50" placeholder="Title" class="form-control" required>
                </div>
                <div class="col-auto">
                    <input type="text" name="description" maxlength="1000" placeholder="Description (optional)" class="form-control">
                </div>
                <div class="col-auto">
                    <select name="idPage" class="form-select">
                        <option value="">No Page</option>
                        @foreach ($pages as $page)
                            <option value="{{ $page->idListPage }}">{{ $page->Title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <input type="number" name="order" placeholder="Order" class="form-control" style="width:100px">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </section>

        <section id="combos" class="card combosuki-main-reversed text-white p-3 mb-4">
            <h4>Combos</h4>
            <p class="text-white-50">
                Drag a combo card into a different category to reassign it (also moves it to that category's page).
                <a href="{{ route('lists.manage.combos.index', $list) }}" class="link-light">Bulk-add more combos &rarr;</a>
            </p>

            @php
                $boardCategories = collect([['id' => 0, 'title' => 'No Category']])
                    ->concat($categories->map(fn ($category) => ['id' => $category->idlist_category, 'title' => $category->title]));
            @endphp

            <div id="combo-board" data-list-id="{{ $list->idlist }}" class="row g-3">
                @foreach ($boardCategories as $category)
                    @php $combos = $grouped->get($category['id'], collect()); @endphp
                    <div class="col-md-4">
                        <div class="category-dropzone border rounded p-2 h-100" data-category-id="{{ $category['id'] === 0 ? '' : $category['id'] }}">
                            <h6>{{ $category['title'] }}</h6>
                            <div class="combo-list">
                                @foreach ($combos as $combo)
                                    <div class="combo-card border rounded p-2 mb-2" draggable="true" data-combo-id="{{ $combo->idcombo }}">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="text-white-50 small">{{ $combo->character->name }}</div>
                                                <x-combo-link :combo="$combo" />
                                                <div class="text-white-50 small">By {{ $combo->user?->nickname ?? 'Anonymous' }}</div>
                                            </div>
                                            <form method="post" action="{{ route('lists.entries.alter', $list) }}" onsubmit="return confirm('Remove this combo from the list?');">
                                                @csrf
                                                <input type="hidden" name="comboid" value="{{ $combo->idcombo }}">
                                                <button type="submit" class="btn btn-sm btn-outline-light">&times;</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <x-edit-history :histories="$history" />
    </div>

    @vite(['resources/js/list-manage.js'])
</x-layouts.app>
