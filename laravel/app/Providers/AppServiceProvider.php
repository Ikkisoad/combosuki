<?php

namespace App\Providers;

use App\Models\Combo;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\ListModel;
use App\Models\SiteSetting;
use App\Policies\ComboPolicy;
use App\Policies\GamePolicy;
use App\Policies\ListPolicy;
use App\Policies\MatchPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Schema\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Builder::defaultStringLength(191);

        // SiteSetting::current() memoises for the life of the request; boot()
        // runs once per request (and once per test), so this is where the memo
        // gets a clean slate.
        SiteSetting::forgetCurrent();

        Paginator::useBootstrapFive();

        Gate::policy(Combo::class, ComboPolicy::class);
        Gate::policy(ListModel::class, ListPolicy::class);
        Gate::policy(GameMatch::class, MatchPolicy::class);
        Gate::policy(Game::class, GamePolicy::class);

        // Socialite ships no Discord driver; socialiteproviders/discord adds
        // one through this event rather than a config entry.
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', Provider::class);
        });

        // Fires for both password login and Discord login (both end in
        // Auth::login — see AuthController::login and
        // DiscordAuthController::signIn), so this one listener covers every
        // sign-in path, including the one completed via the two-factor
        // challenge (TwoFactorChallengeController::store).
        Event::listen(function (Login $event) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        });

        // @vite()/Vite::asset() default to an ABSOLUTE URL built from the
        // current request's own host (via the global asset() helper) —
        // fine for a normal same-origin page load, but comble.show also
        // renders inside a real Discord client, where the page is actually
        // displayed from a discordsays.com proxy origin while our server
        // still sees the request as comble.combosuki.com. Baking that host
        // into <script>/<link> tags makes the browser, sandboxed inside
        // Discord's Activity iframe, try to fetch an entirely different
        // external domain directly — which that sandbox blocks by design
        // (confirmed in production: "Failed to fetch" on every asset).
        // Root-relative paths sidestep the whole problem: the browser
        // resolves them against whatever origin is actually serving the
        // page, which is correct in every context — a normal visit, the
        // dedicated subdomain directly, or proxied through Discord.
        Vite::createAssetPathsUsing(fn (string $path) => '/'.ltrim($path, '/'));
    }
}
