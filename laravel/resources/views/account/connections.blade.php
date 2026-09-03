<x-layouts.app title="Connected Accounts - Combo好き">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container-fluid my-3">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2>Connected Accounts</h2>

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

                <div class="card combosuki-main-reversed text-white p-3">
                    <h5 class="mb-2">Discord</h5>

                    @unless ($integrationEnabled)
                        <div class="alert alert-warning mb-3">
                            Discord integration is currently unavailable. You can't connect a new
                            account right now, but an existing connection can still be removed.
                        </div>
                    @endunless

                    @if (! $hasPassword && ! $discordAccount)
                        <p class="mb-1">No Discord account connected.</p>
                        <p class="text-white-50 mb-0">
                            Your account doesn't have a password set yet, so there's no way to confirm
                            it's you. <a class="link-light" href="{{ route('password.edit') }}">Set a password</a>
                            before connecting Discord.
                        </p>
                    @elseif ($discordAccount)
                        <p class="mb-1">
                            Connected as <strong>{{ $discordAccount->provider_nickname ?? 'Unknown' }}</strong>
                        </p>
                        <p class="text-white-50 mb-3">
                            Connected {{ $discordAccount->created_at?->format('M j, Y') }}
                        </p>

                        @if (! $hasPassword)
                            <p class="text-white-50 mb-0">
                                Discord is currently the only way into your account, so it can't be
                                disconnected. <a class="link-light" href="{{ route('password.edit') }}">Set a password</a>
                                first.
                            </p>
                        @else
                            <form method="post" action="{{ route('connections.discord.destroy') }}">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label">Confirm your password to disconnect</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-outline-light">Disconnect Discord</button>
                            </form>
                        @endif
                    @else
                        <p class="mb-1">No Discord account connected.</p>
                        <p class="text-white-50 mb-3">
                            Your Discord account needs a verified email address, and it can only be
                            connected to one Combo好き account.
                        </p>

                        @if ($integrationEnabled)
                            <form method="post" action="{{ route('connections.discord.redirect') }}">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label">Confirm your password to continue</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-combosuki">Connect Discord</button>
                            </form>
                        @endif
                    @endif
                </div>

                <p class="mt-3 mb-0">
                    <a href="{{ route('password.edit') }}">Change your password</a>
                    &middot;
                    <a href="{{ route('two-factor.edit') }}">Two-factor authentication</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
