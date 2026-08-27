<x-layouts.app title="Change Password - Combo好き">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container-fluid my-3">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <h2>Change Password</h2>

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

                <form method="post" action="{{ route('password.update') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-2">
                        <button type="submit" class="btn btn-primary btn-block">Update Password</button>
                    </div>
                </form>

                <p class="mt-3 mb-0">
                    <a href="{{ route('connections.edit') }}">Connected accounts</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
