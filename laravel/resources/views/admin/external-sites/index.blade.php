<x-layouts.app title="Edit Other FGC Websites">
    <x-nav-bar />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
            <caption>Other FGC websites (shown on the About page)</caption>
            <tr><th>Title</th><th>URL</th><th>Order</th></tr>
            @foreach ($sites as $site)
                <tr>
                    <td colspan="3">
                        <form method="post" action="{{ route('admin.external-sites.store') }}">
                            @csrf
                            <div class="input-group">
                                <textarea name="title" maxlength="100" class="form-control" rows="1">{{ $site->title }}</textarea>
                                <textarea name="url" maxlength="255" class="form-control" rows="1">{{ $site->url }}</textarea>
                                <input type="number" name="order" class="form-control" style="max-width: 6rem;" value="{{ $site->order }}">
                                <input type="hidden" name="id" value="{{ $site->id }}">
                                <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
                                <button type="submit" name="action" value="Delete" class="btn btn-danger" data-confirm="Are you sure you want to delete this site?">Delete</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3">
                    <form method="post" action="{{ route('admin.external-sites.store') }}">
                        @csrf
                        <div class="input-group">
                            <textarea name="title" maxlength="100" class="form-control" rows="1" placeholder="Site Title" autofocus></textarea>
                            <textarea name="url" maxlength="255" class="form-control" rows="1" placeholder="Site URL"></textarea>
                            <input type="number" name="order" class="form-control" style="max-width: 6rem;" placeholder="Order">
                            <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                </td>
            </tr>
        </table>
    </div>
</x-layouts.app>
