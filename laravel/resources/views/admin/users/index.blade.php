<x-layouts.app title="Manage Users">
    <x-nav-bar />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <h1 class="text-white">Manage Users</h1>
        <p class="text-white">
            Create and review user accounts.
            @if (auth()->user()->is_admin)
                <a href="{{ route('admin.dashboard') }}" class="link-light">&larr; Back to Admin Dashboard</a>
            @endif
        </p>

        <div class="row g-3">
            @if (auth()->user()->is_admin)
                <div class="col-lg-4">
                    <div class="card combosuki-main-reversed text-white p-3">
                        <h2 class="h5">Create User</h2>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="post" action="{{ route('admin.users.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="nickname" class="form-label">Nickname</label>
                                <input type="text" name="nickname" id="nickname" class="form-control" value="{{ old('nickname') }}" maxlength="45" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control" minlength="8" required>
                            </div>
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirm Password</label>
                                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" minlength="8" required>
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="is_admin" value="1" id="is_admin" class="form-check-input" @checked(old('is_admin'))>
                                <label class="form-check-label" for="is_admin">Admin</label>
                            </div>
                            <div class="form-check mb-3">
                                <input type="checkbox" name="trusted_user" value="1" id="trusted_user" class="form-check-input" @checked(old('trusted_user'))>
                                <label class="form-check-label" for="trusted_user">Trusted user</label>
                            </div>
                            <button type="submit" class="btn btn-primary">Create User</button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="{{ auth()->user()->is_admin ? 'col-lg-8' : 'col-lg-12' }}">
                <div class="card combosuki-main-reversed text-white p-3">
                    <h2 class="h5">Existing Users <small class="text-white-50">({{ $users->total() }})</small></h2>

                    <form method="get" action="{{ route('admin.users.index') }}" class="row g-2 align-items-end mb-3">
                        <div class="col-auto">
                            <label class="form-label">Search</label>
                            <input type="text" name="user_search" class="form-control" value="{{ request('user_search') }}" placeholder="nickname">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle combosuki-main-reversed text-white">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nickname</th>
                                    <th>Admin</th>
                                    <th>Trusted</th>
                                    @if (auth()->user()->is_admin)
                                        <th>Moderator</th>
                                    @endif
                                    <th>Joined</th>
                                    <th>Last Login</th>
                                    @if (auth()->user()->is_admin)
                                        <th>Password</th>
                                        <th>Two-Factor</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $user)
                                    <tr>
                                        <td>{{ $user->iduser }}</td>
                                        <td>{{ $user->nickname }}</td>
                                        <td>{{ $user->is_admin ? 'Yes' : 'No' }}</td>
                                        <td>
                                            {{ $user->trusted_user ? 'Yes' : 'No' }}
                                            <form method="post" action="{{ route('admin.users.trusted.update', $user) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-light">
                                                    {{ $user->trusted_user ? 'Revoke' : 'Trust' }}
                                                </button>
                                            </form>
                                        </td>
                                        @if (auth()->user()->is_admin)
                                            <td>
                                                {{ $user->is_moderator ? 'Yes' : 'No' }}
                                                <form method="post" action="{{ route('admin.users.moderator.update', $user) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-light">
                                                        {{ $user->is_moderator ? 'Revoke' : 'Make Moderator' }}
                                                    </button>
                                                </form>
                                                @if ($user->is_moderator)
                                                    <a href="{{ route('admin.users.moderated-games.edit', $user) }}" class="btn btn-sm btn-outline-light">Manage games</a>
                                                @endif
                                            </td>
                                        @endif
                                        <td>{{ $user->created_at?->format('Y-m-d') }}</td>
                                        <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                                        @if (auth()->user()->is_admin)
                                            <td>
                                                <details>
                                                    <summary class="btn btn-sm btn-outline-light">Change</summary>
                                                    <form method="post" action="{{ route('admin.users.password.update', $user) }}" class="mt-2" style="min-width: 220px;">
                                                        @csrf
                                                        <div class="mb-2">
                                                            <input type="password" name="password" class="form-control form-control-sm" placeholder="New password" minlength="8" required>
                                                        </div>
                                                        <div class="mb-2">
                                                            <input type="password" name="password_confirmation" class="form-control form-control-sm" placeholder="Confirm password" minlength="8" required>
                                                        </div>
                                                        <button type="submit" class="btn btn-sm btn-primary">Update Password</button>
                                                    </form>
                                                </details>
                                            </td>
                                            <td>
                                                @if ($user->hasTwoFactorEnabled())
                                                    Enabled
                                                    <form method="post" action="{{ route('admin.users.two-factor.destroy', $user) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-light">Disable</button>
                                                    </form>
                                                @else
                                                    Off
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="7">No users found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
