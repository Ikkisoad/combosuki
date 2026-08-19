<x-layouts.app :title="'Create User - Combo好き'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2>Create User</h2>

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

                <form method="post" action="{{ route('users.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Nickname</label>
                        <input type="text" name="nickname" class="form-control" value="{{ old('nickname') }}" maxlength="45" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-2">
                        <button type="submit" class="btn btn-combosuki">Create User</button>
                    </div>
                </form>

                <p class="mt-3">This creates a regular user account. New accounts are never trusted or admin — ask an admin to promote them afterwards.</p>
            </div>
        </div>
    </div>
</x-layouts.app>
