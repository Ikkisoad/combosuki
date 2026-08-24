<?php

namespace App\Providers;

use App\Models\Combo;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\ListModel;
use App\Policies\ComboPolicy;
use App\Policies\GamePolicy;
use App\Policies\ListPolicy;
use App\Policies\MatchPolicy;
use Illuminate\Database\Schema\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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

        Paginator::useBootstrapFive();

        Gate::policy(Combo::class, ComboPolicy::class);
        Gate::policy(ListModel::class, ListPolicy::class);
        Gate::policy(GameMatch::class, MatchPolicy::class);
        Gate::policy(Game::class, GamePolicy::class);
    }
}
