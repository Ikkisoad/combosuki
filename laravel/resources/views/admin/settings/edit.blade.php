<x-layouts.app title="Site Settings - Admin">
    <x-nav-bar />

    <div class="container my-3">
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

        <h1 class="text-white mb-4">Site Settings</h1>

        <form method="post" action="{{ route('admin.settings.update') }}" class="card combosuki-main-reversed text-white p-3">
            @csrf

            <h2 class="h5 mb-3">Discord</h2>

            <div class="form-check mb-3">
                <input type="checkbox" name="discord_integration_enabled" id="discord_integration_enabled"
                       class="form-check-input" value="1"
                       @checked(old('discord_integration_enabled', $settings->discord_integration_enabled))>
                <label class="form-check-label" for="discord_integration_enabled">
                    Enable Discord integration on the website
                </label>
            </div>

            <p class="text-white-50">
                Covers signing in with Discord, creating an account with Discord, and connecting or
                disconnecting Discord from an existing account. The Discord <strong>bot</strong>
                (slash commands, Comble) is not affected by this setting.
            </p>

            <div class="alert alert-warning">
                <strong>Turning this off locks out accounts that only have Discord.</strong>
                Anyone who registered through Discord and has not set a password will be unable to
                sign in at all until it is turned back on. Existing connections are kept, not deleted.
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" name="discord_activity_enabled" id="discord_activity_enabled"
                       class="form-check-input" value="1"
                       @checked(old('discord_activity_enabled', $settings->discord_activity_enabled))>
                <label class="form-check-label" for="discord_activity_enabled">
                    Enable the Comble Discord Activity
                </label>
            </div>

            <p class="text-white-50">
                The embedded Comble game launched from a Discord voice channel. Independent of the
                integration switch above — turning that one off also takes this down, but this can be
                turned off on its own without affecting Discord sign-in or account linking.
            </p>

            <div>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</x-layouts.app>
