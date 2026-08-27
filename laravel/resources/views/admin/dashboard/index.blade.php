<x-layouts.app title="Admin Dashboard">
    <x-nav-bar />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <h1 class="text-white mb-4">Admin Dashboard</h1>

        <div class="row g-4">
            <div class="col-md-4">
                <a href="{{ route('admin.users.index') }}" class="text-decoration-none">
                    <div class="card combosuki-main-reversed text-white text-center p-4 h-100 admin-dashboard-tile">
                        <div class="display-4 mb-2">👤</div>
                        <h2 class="h4 mb-1">Users</h2>
                        <p class="text-white-50 mb-0">Create and manage user accounts</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.analytics') }}" class="text-decoration-none">
                    <div class="card combosuki-main-reversed text-white text-center p-4 h-100 admin-dashboard-tile">
                        <div class="display-4 mb-2">📊</div>
                        <h2 class="h4 mb-1">Analytics</h2>
                        <p class="text-white-50 mb-0">View site-wide traffic and stats</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.data-management') }}" class="text-decoration-none">
                    <div class="card combosuki-main-reversed text-white text-center p-4 h-100 admin-dashboard-tile">
                        <div class="display-4 mb-2">🗄️</div>
                        <h2 class="h4 mb-1">Data Management</h2>
                        <p class="text-white-50 mb-0">Search and bulk-delete combos, lists, and games</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.donation.edit') }}" class="text-decoration-none">
                    <div class="card combosuki-main-reversed text-white text-center p-4 h-100 admin-dashboard-tile">
                        <div class="display-4 mb-2">💸</div>
                        <h2 class="h4 mb-1">Donation Progress</h2>
                        <p class="text-white-50 mb-0">Update the monthly server-costs donation bar</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.external-sites.index') }}" class="text-decoration-none">
                    <div class="card combosuki-main-reversed text-white text-center p-4 h-100 admin-dashboard-tile">
                        <div class="display-4 mb-2">🔗</div>
                        <h2 class="h4 mb-1">Other FGC Websites</h2>
                        <p class="text-white-50 mb-0">Manage the external site links shown on the About page</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('admin.settings.edit') }}" class="text-decoration-none">
                    <div class="card combosuki-main-reversed text-white text-center p-4 h-100 admin-dashboard-tile">
                        <div class="display-4 mb-2">⚙️</div>
                        <h2 class="h4 mb-1">Site Settings</h2>
                        <p class="text-white-50 mb-0">Turn site-wide features on or off</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <style>
        .admin-dashboard-tile {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .admin-dashboard-tile:hover {
            transform: translateY(-4px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.3);
        }
    </style>
</x-layouts.app>
