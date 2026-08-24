<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ButtonController;
use App\Http\Controllers\Admin\CharacterController as AdminCharacterController;
use App\Http\Controllers\Admin\CharacterQueryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DonationController;
use App\Http\Controllers\Admin\ExternalSiteController;
use App\Http\Controllers\Admin\GameEntryController;
use App\Http\Controllers\Admin\GameListController;
use App\Http\Controllers\Admin\GameResourceController;
use App\Http\Controllers\Admin\GameSettingsController;
use App\Http\Controllers\Admin\LinkController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CombleController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\ComboListController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\ListCategoryController;
use App\Http\Controllers\ListComboPickerController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\ListPageController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TierListController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\UserController;
use App\Models\Combo;
use App\Models\ExternalSite;
use App\Models\Game;
use App\Services\DailyChallenge;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $games = Game::where('complete', '>', 0)->orderByDesc('views')->limit(12)->get();
    $challenge = app(DailyChallenge::class)->today();

    return view('home', ['games' => $games, 'challenge' => $challenge]);
})->name('home');

Route::get('/about', function () {
    return view('about', [
        'comboCount' => Combo::count(),
        'externalSites' => ExternalSite::orderBy('order')->orderBy('title')->get(),
    ]);
})->name('about');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::post('/account/password', [PasswordController::class, 'update'])->name('password.update');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard.index')->name('dashboard');

    Route::get('/data-management', [DashboardController::class, 'index'])->name('data-management');
    Route::post('/data-management/destroy', [DashboardController::class, 'destroy'])->middleware('throttle:10,1')->name('data-management.destroy');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->middleware('throttle:10,1')->name('users.store');
    Route::post('/users/{user}/password', [AdminUserController::class, 'updatePassword'])->middleware('throttle:10,1')->name('users.password.update');
    Route::post('/users/{user}/trusted', [AdminUserController::class, 'updateTrusted'])->middleware('throttle:10,1')->name('users.trusted.update');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

    Route::get('/donation', [DonationController::class, 'edit'])->name('donation.edit');
    Route::put('/donation', [DonationController::class, 'update'])->middleware('throttle:10,1')->name('donation.update');

    Route::get('/external-sites', [ExternalSiteController::class, 'index'])->name('external-sites.index');
    Route::post('/external-sites', [ExternalSiteController::class, 'store'])->middleware('throttle:10,1')->name('external-sites.store');
});

Route::middleware(['auth', 'trusted'])->group(function () {
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('throttle:10,1')->name('users.store');
});

Route::get('/users/search', [UserController::class, 'search'])->middleware('auth')->name('users.search');
Route::get('/users/{user}', [ProfileController::class, 'show'])->name('users.show');

Route::get('/games', [GameController::class, 'index'])->name('games.index');
Route::get('/games/add', [GameController::class, 'create'])->middleware(['auth', 'trusted'])->name('games.create');
Route::post('/games', [GameController::class, 'store'])->middleware(['auth', 'trusted', 'throttle:10,1'])->name('games.store');
Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
Route::get('/games/{game}/tabs/guides', [GameController::class, 'guidesTab'])->name('games.tabs.guides');
Route::get('/games/{game}/tabs/tier-lists', [GameController::class, 'tierListsTab'])->name('games.tabs.tier-lists');
Route::get('/games/{game}/tabs/most-viewed', [GameController::class, 'mostViewedTab'])->name('games.tabs.most-viewed');
Route::get('/games/{game}/tabs/damage-stats', [GameController::class, 'damageStatsTab'])->name('games.tabs.damage-stats');
Route::get('/games/{game}/tabs/matches', [GameController::class, 'matchesTab'])->name('games.tabs.matches');

Route::scopeBindings()->group(function () {
    Route::get('/games/{game}/characters/{character}', [CharacterController::class, 'show'])->name('characters.show');
});

Route::get('/games/{game}/combos', [ComboController::class, 'index'])->name('games.combos.index');
Route::get('/games/{game}/combos/add', [ComboController::class, 'create'])->middleware('auth')->name('games.combos.create');
Route::post('/games/{game}/combos', [ComboController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('games.combos.store');
Route::get('/combos/{combo}', [ComboController::class, 'show'])->name('combos.show');
Route::get('/combos/{combo}/edit', [ComboController::class, 'edit'])->middleware('auth')->name('combos.edit');
Route::post('/combos/{combo}/edit', [ComboController::class, 'update'])->middleware(['auth', 'throttle:10,1'])->name('combos.update');
Route::post('/combos/{combo}/delete', [ComboController::class, 'destroy'])->middleware(['auth', 'throttle:10,1'])->name('combos.destroy');
Route::post('/combos/{combo}/lists/{list}', [ComboListController::class, 'store'])->middleware(['auth', 'throttle:60,1'])->name('combos.lists.store');
Route::post('/combos/{combo}/favorite', [FavoriteController::class, 'store'])->middleware(['auth', 'throttle:60,1'])->name('favorites.store');
Route::post('/combos/{combo}/unfavorite', [FavoriteController::class, 'destroy'])->middleware(['auth', 'throttle:60,1'])->name('favorites.destroy');

Route::get('/games/{game}/matches', [MatchController::class, 'index'])->name('games.matches.index');
Route::get('/games/{game}/matches/add', [MatchController::class, 'create'])->middleware('auth')->name('games.matches.create');
Route::post('/games/{game}/matches', [MatchController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('games.matches.store');
Route::get('/matches/{gameMatch}/edit', [MatchController::class, 'edit'])->middleware('auth')->name('matches.edit');
Route::post('/matches/{gameMatch}/edit', [MatchController::class, 'update'])->middleware(['auth', 'throttle:10,1'])->name('matches.update');
Route::post('/matches/{gameMatch}/delete', [MatchController::class, 'destroy'])->middleware(['auth', 'throttle:10,1'])->name('matches.destroy');

Route::get('/lists', [ListController::class, 'index'])->name('lists.index');
Route::get('/lists/search', [ListController::class, 'search'])->name('lists.search');
Route::post('/lists', [ListController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('lists.store');
Route::get('/lists/{list}', [ListController::class, 'show'])->name('lists.show');
Route::post('/lists/{list}/rename', [ListController::class, 'rename'])->middleware(['auth', 'throttle:10,1'])->name('lists.rename');
Route::post('/lists/{list}/delete', [ListController::class, 'destroy'])->middleware(['auth', 'throttle:10,1'])->name('lists.destroy');
Route::post('/lists/{list}/entries', [ListController::class, 'alterEntries'])->middleware(['auth', 'throttle:10,1'])->name('lists.entries.alter');
Route::patch('/lists/{list}/entries/{combo}/category', [ListController::class, 'reassignEntry'])->middleware(['auth', 'throttle:60,1'])->name('lists.entries.reassign');

Route::middleware(['auth', 'throttle:10,1'])->prefix('lists/{list}/manage')->name('lists.manage.')->scopeBindings()->group(function () {
    Route::get('/', [ListController::class, 'manage'])->withoutMiddleware('throttle:10,1')->name('index');

    Route::post('/pages', [ListPageController::class, 'store'])->name('pages.store');
    Route::post('/pages/bulk', [ListPageController::class, 'bulkUpdate'])->name('pages.bulk');
    Route::post('/pages/{page}/delete', [ListPageController::class, 'destroy'])->name('pages.destroy');

    Route::post('/categories', [ListCategoryController::class, 'store'])->name('categories.store');
    Route::post('/categories/bulk', [ListCategoryController::class, 'bulkUpdate'])->name('categories.bulk');
    Route::post('/categories/{category}/delete', [ListCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/combos', [ListComboPickerController::class, 'index'])->withoutMiddleware('throttle:10,1')->name('combos.index');
    Route::post('/combos', [ListComboPickerController::class, 'store'])->name('combos.store');
});

Route::get('/tier-lists', [TierListController::class, 'index'])->name('tier-lists.index');
Route::get('/tier-lists/create', [TierListController::class, 'create'])->middleware('auth')->name('tier-lists.create');
Route::post('/tier-lists', [TierListController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('tier-lists.store');
Route::get('/tier-lists/{tierList}', [TierListController::class, 'show'])->name('tier-lists.show');

Route::view('/combo-guidelines', 'combo-guidelines')->name('combo-guidelines');

Route::get('/logs', [LogController::class, 'index'])->name('logs.index');

Route::get('/preferences', [PreferenceController::class, 'edit'])->name('preferences.edit');
Route::post('/preferences', [PreferenceController::class, 'update'])->middleware('throttle:20,1')->name('preferences.update');

Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline.index');

Route::get('/comble', [CombleController::class, 'show'])->name('comble.show');
Route::post('/comble/guess', [CombleController::class, 'guess'])->middleware('throttle:20,1')->name('comble.guess');
Route::get('/comble/{date}', [CombleController::class, 'show'])->where('date', '\d{4}-\d{2}-\d{2}')->name('comble.show.date');
Route::post('/comble/{date}/guess', [CombleController::class, 'guess'])->where('date', '\d{4}-\d{2}-\d{2}')->middleware('throttle:20,1')->name('comble.guess.date');

Route::view('/randomizer', 'randomizer.index')->name('randomizer.index');
Route::view('/randomizer/dbfz', 'randomizer.dbfz')->name('randomizer.dbfz');
Route::view('/randomizer/mvc2', 'randomizer.mvc2')->name('randomizer.mvc2');
Route::view('/randomizer/skullgirls', 'randomizer.skullgirls')->name('randomizer.skullgirls');
Route::view('/randomizer/dengeki', 'randomizer.dengeki')->name('randomizer.dengeki');

Route::middleware(['auth', 'trusted'])->prefix('games/{game}/edit')->name('admin.')->scopeBindings()->group(function () {
    Route::get('/', [GameSettingsController::class, 'edit'])->name('game.edit');
    Route::post('/', [GameSettingsController::class, 'update'])->middleware('throttle:10,1')->name('game.update');

    Route::get('/characters', [AdminCharacterController::class, 'index'])->name('characters.index');
    Route::post('/characters', [AdminCharacterController::class, 'store'])->middleware('throttle:10,1')->name('characters.store');
    Route::post('/characters/bulk', [AdminCharacterController::class, 'bulkUpdate'])->middleware('throttle:10,1')->name('characters.bulkUpdate');

    Route::get('/queries', [CharacterQueryController::class, 'index'])->name('queries.index');
    Route::post('/queries', [CharacterQueryController::class, 'store'])->middleware('throttle:10,1')->name('queries.store');

    Route::get('/links', [LinkController::class, 'index'])->name('links.index');
    Route::post('/links', [LinkController::class, 'store'])->middleware('throttle:10,1')->name('links.store');

    Route::get('/entries', [GameEntryController::class, 'index'])->name('entries.index');
    Route::post('/entries', [GameEntryController::class, 'store'])->middleware('throttle:10,1')->name('entries.store');

    Route::get('/buttons', [ButtonController::class, 'index'])->name('buttons.index');
    Route::post('/buttons', [ButtonController::class, 'store'])->middleware('throttle:10,1')->name('buttons.store');
    Route::post('/buttons/bulk', [ButtonController::class, 'bulkUpdate'])->middleware('throttle:10,1')->name('buttons.bulkUpdate');

    Route::get('/resources', [GameResourceController::class, 'index'])->name('resources.index');
    Route::post('/resources', [GameResourceController::class, 'store'])->middleware('throttle:10,1')->name('resources.store');
    Route::get('/resources/{resource}', [GameResourceController::class, 'values'])->name('resources.values');
    Route::post('/resources/{resource}', [GameResourceController::class, 'storeValue'])->middleware('throttle:10,1')->name('resources.values.store');

    Route::get('/lists', [GameListController::class, 'index'])->name('lists.index');
    Route::post('/lists', [GameListController::class, 'store'])->middleware('throttle:10,1')->name('lists.store');
});
