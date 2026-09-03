<x-layouts.app title="Two-Factor Authentication - Combo好き">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container-fluid my-3">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <h2>Two-Factor Authentication</h2>

                <p>Enter the code from your authenticator app to finish logging in.</p>

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

                <form method="post" action="{{ route('two-factor.challenge.attempt') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" class="form-control" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" autofocus required>
                    </div>
                    <div class="mb-2">
                        <button type="submit" class="btn btn-primary btn-block">Verify</button>
                    </div>
                </form>

                <p class="mt-3 mb-0">
                    <a href="{{ route('login') }}">Back to log in</a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
