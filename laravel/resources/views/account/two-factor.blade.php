<x-layouts.app title="Two-Factor Authentication - Combo好き">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container-fluid my-3">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2>Two-Factor Authentication</h2>

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
                    @if ($enabled)
                        <h5 class="mb-2">Enabled</h5>
                        <p class="mb-3">
                            Your account is protected with an authenticator app. You'll be asked for a
                            code every time you log in with your password.
                        </p>

                        <p class="text-white-50 mb-3">
                            There are no backup codes for this — if you lose access to your
                            authenticator app, an admin will need to disable this for you.
                        </p>

                        <form method="post" action="{{ route('two-factor.disable') }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">Confirm your password to disable</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-outline-light">Disable Two-Factor Authentication</button>
                        </form>
                    @elseif ($pending)
                        <h5 class="mb-2">Finish setting up</h5>
                        <p class="mb-3">
                            Scan this QR code with an authenticator app (Authy, Google Authenticator, or
                            similar), then enter the 6-digit code it shows to confirm.
                        </p>

                        <div class="bg-white p-3 mb-3 d-inline-block" style="max-width: 220px;">
                            {!! $qrCodeSvg !!}
                        </div>

                        <p class="text-white-50 mb-3">
                            Can't scan it? Enter this key manually: <code class="text-white">{{ $secret }}</code>
                        </p>

                        <form method="post" action="{{ route('two-factor.confirm') }}" class="mb-3">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">Code</label>
                                <input type="text" name="code" class="form-control" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" autofocus required>
                            </div>
                            <button type="submit" class="btn btn-combosuki">Confirm and Enable</button>
                        </form>

                        <form method="post" action="{{ route('two-factor.disable') }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">Confirm your password to cancel setup</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-outline-light">Cancel</button>
                        </form>
                    @else
                        <h5 class="mb-2">Not enabled</h5>
                        <p class="mb-3">
                            Add a second step to your password login using an authenticator app like
                            Authy or Google Authenticator.
                        </p>

                        <form method="post" action="{{ route('two-factor.enable') }}">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">Confirm your password to continue</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-combosuki">Enable Two-Factor Authentication</button>
                        </form>
                    @endif
                </div>

                <p class="mt-3 mb-0">
                    <a href="{{ route('password.edit') }}">Change your password</a>
                    &middot;
                    <a href="{{ route('connections.edit') }}">Connected accounts</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
