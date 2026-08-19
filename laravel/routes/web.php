<?php

use App\Http\Controllers\Admin\ButtonController;
use App\Http\Controllers\Admin\CharacterController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GameEntryController;
use App\Http\Controllers\Admin\GameListController;
use App\Http\Controllers\Admin\GameResourceController;
use App\Http\Controllers\Admin\GameSettingsController;
use App\Http\Controllers\Admin\LinkController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\TimelineController;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $games = Game::where('complete', '>', 0)->orderBy('name')->get();

    return view('home', ['games' => $games]);
})->name('home');

Route::get('/about', function () {
    return view('about', ['comboCount' => Combo::count()]);
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
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/destroy', [DashboardController::class, 'destroy'])->middleware('throttle:10,1')->name('dashboard.destroy');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware('throttle:10,1')->name('users.store');
    Route::post('/users/{user}/password', [UserController::class, 'updatePassword'])->middleware('throttle:10,1')->name('users.password.update');
});

Route::get('/games', [GameController::class, 'index'])->name('games.index');
Route::get('/games/add', [GameController::class, 'create'])->middleware('auth')->name('games.create');
Route::post('/games', [GameController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('games.store');
Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');

Route::get('/games/{game}/combos', [ComboController::class, 'index'])->name('games.combos.index');
Route::get('/games/{game}/combos/add', [ComboController::class, 'create'])->middleware('auth')->name('games.combos.create');
Route::post('/games/{game}/combos', [ComboController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('games.combos.store');
Route::get('/combos/{combo}', [ComboController::class, 'show'])->name('combos.show');
Route::get('/combos/{combo}/edit', [ComboController::class, 'edit'])->middleware('auth')->name('combos.edit');
Route::post('/combos/{combo}/edit', [ComboController::class, 'update'])->middleware(['auth', 'throttle:10,1'])->name('combos.update');
Route::post('/combos/{combo}/delete', [ComboController::class, 'destroy'])->middleware(['auth', 'throttle:10,1'])->name('combos.destroy');

Route::get('/lists', [ListController::class, 'index'])->name('lists.index');
Route::get('/lists/search', [ListController::class, 'search'])->name('lists.search');
Route::post('/lists', [ListController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('lists.store');
Route::get('/lists/{list}', [ListController::class, 'show'])->name('lists.show');
Route::post('/lists/{list}/rename', [ListController::class, 'rename'])->middleware(['auth', 'throttle:10,1'])->name('lists.rename');
Route::post('/lists/{list}/delete', [ListController::class, 'destroy'])->middleware(['auth', 'throttle:10,1'])->name('lists.destroy');
Route::post('/lists/{list}/entries', [ListController::class, 'alterEntries'])->middleware(['auth', 'throttle:10,1'])->name('lists.entries.alter');

Route::view('/matches', 'matches.index')->name('matches.index');

Route::view('/combo-guidelines', 'combo-guidelines')->name('combo-guidelines');

Route::get('/logs', [LogController::class, 'index'])->name('logs.index');

Route::get('/preferences', [PreferenceController::class, 'edit'])->name('preferences.edit');
Route::post('/preferences', [PreferenceController::class, 'update'])->middleware('throttle:20,1')->name('preferences.update');

Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline.index');

Route::middleware('auth')->prefix('games/{game}/edit')->name('admin.')->scopeBindings()->group(function () {
    Route::get('/', [GameSettingsController::class, 'edit'])->name('game.edit');
    Route::post('/', [GameSettingsController::class, 'update'])->middleware('throttle:10,1')->name('game.update');

    Route::get('/characters', [CharacterController::class, 'index'])->name('characters.index');
    Route::post('/characters', [CharacterController::class, 'store'])->middleware('throttle:10,1')->name('characters.store');

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
