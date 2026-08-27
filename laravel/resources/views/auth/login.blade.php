<x-layouts.app title="Log In - Combo好き">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container-fluid my-3">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <h2>Log In</h2>

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

                <form method="post" action="{{ route('login.attempt') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Nickname</label>
                        <input type="text" name="nickname" class="form-control" value="{{ old('nickname') }}" required autofocus>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <button type="submit" class="btn btn-primary btn-block">Log In</button>
                    </div>
                </form>

                @if (\App\Models\SiteSetting::discordIntegrationEnabled())
                    <hr>

                    <form method="post" action="{{ route('auth.discord.redirect') }}">
                        @csrf
                        <button type="submit" class="btn btn-combosuki btn-block">Continue with Discord</button>
                    </form>

                    <p class="form-text mt-2 mb-0">
                        New here? Continuing with Discord creates your account. Your Discord account
                        needs a verified email address.
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
