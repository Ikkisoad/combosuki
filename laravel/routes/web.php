<?php

use App\Http\Controllers\Admin\ButtonController;
use App\Http\Controllers\Admin\CharacterController;
use App\Http\Controllers\Admin\GameEntryController;
use App\Http\Controllers\Admin\GameListController;
use App\Http\Controllers\Admin\GameResourceController;
use App\Http\Controllers\Admin\GameSettingsController;
use App\Http\Controllers\Admin\LinkController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\TimelineController;
use App\Models\Game;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $games = Game::where('complete', '>', 0)->orderBy('name')->get();

    return view('home', ['games' => $games]);
})->name('home');

Route::get('/games', [GameController::class, 'index'])->name('games.index');
Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');

Route::get('/games/{game}/combos/add', [ComboController::class, 'create'])->name('games.combos.create');
Route::post('/games/{game}/combos', [ComboController::class, 'store'])->name('games.combos.store');
Route::get('/combos/{combo}', [ComboController::class, 'show'])->name('combos.show');

Route::get('/lists', [ListController::class, 'index'])->name('lists.index');
Route::get('/lists/search', [ListController::class, 'search'])->name('lists.search');
Route::post('/lists', [ListController::class, 'store'])->name('lists.store');
Route::get('/lists/{list}', [ListController::class, 'show'])->name('lists.show');
Route::post('/lists/{list}/rename', [ListController::class, 'rename'])->name('lists.rename');
Route::post('/lists/{list}/delete', [ListController::class, 'destroy'])->name('lists.destroy');
Route::post('/lists/{list}/entries', [ListController::class, 'alterEntries'])->name('lists.entries.alter');

Route::view('/matches', 'matches.index')->name('matches.index');

Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline.index');

Route::prefix('games/{game}/edit')->name('admin.')->scopeBindings()->group(function () {
    Route::get('/', [GameSettingsController::class, 'edit'])->name('game.edit');
    Route::post('/', [GameSettingsController::class, 'update'])->name('game.update');

    Route::get('/characters', [CharacterController::class, 'index'])->name('characters.index');
    Route::post('/characters', [CharacterController::class, 'store'])->name('characters.store');

    Route::get('/links', [LinkController::class, 'index'])->name('links.index');
    Route::post('/links', [LinkController::class, 'store'])->name('links.store');

    Route::get('/entries', [GameEntryController::class, 'index'])->name('entries.index');
    Route::post('/entries', [GameEntryController::class, 'store'])->name('entries.store');

    Route::get('/buttons', [ButtonController::class, 'index'])->name('buttons.index');
    Route::post('/buttons', [ButtonController::class, 'store'])->name('buttons.store');

    Route::get('/resources', [GameResourceController::class, 'index'])->name('resources.index');
    Route::post('/resources', [GameResourceController::class, 'store'])->name('resources.store');
    Route::get('/resources/{resource}', [GameResourceController::class, 'values'])->name('resources.values');
    Route::post('/resources/{resource}', [GameResourceController::class, 'storeValue'])->name('resources.values.store');

    Route::get('/lists', [GameListController::class, 'index'])->name('lists.index');
    Route::post('/lists', [GameListController::class, 'store'])->name('lists.store');
});
