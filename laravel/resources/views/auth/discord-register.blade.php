<x-layouts.app title="Choose a Nickname - Combo好き">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container-fluid my-3">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <h2>Choose a Nickname</h2>

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

                <p>
                    Signing up as <strong>{{ $discordNickname ?? 'your Discord account' }}</strong>.
                    Pick the nickname you want to use on Combo好き — it's your public name here and
                    how you'll be credited on combos and guides.
                </p>

                <form method="post" action="{{ route('auth.discord.register.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Nickname</label>
                        <input type="text" name="nickname" class="form-control"
                               value="{{ old('nickname', $suggestedNickname) }}"
                               minlength="3" maxlength="45" required autofocus>
                        <div class="form-text">
                            3–45 characters. Letters, numbers, underscore, dot and hyphen only.
                        </div>
                    </div>
                    <div class="mb-2">
                        <button type="submit" class="btn btn-combosuki">Create Account</button>
                    </div>
                </form>

                <p class="mt-3 mb-0">
                    Your account will use Discord to sign in. You can add a password later from your
                    account settings.
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
