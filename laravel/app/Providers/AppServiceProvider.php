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
use Illuminate\Database\Schema\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
    }
}
