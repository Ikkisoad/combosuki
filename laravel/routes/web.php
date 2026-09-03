<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ButtonAliasController;
use App\Http\Controllers\Admin\ButtonController;
use App\Http\Controllers\Admin\CharacterButtonAliasController;
use App\Http\Controllers\Admin\CharacterController as AdminCharacterController;
use App\Http\Controllers\Admin\CharacterQueryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DonationController;
use App\Http\Controllers\Admin\ExternalSiteController;
use App\Http\Controllers\Admin\GameEntryController;
use App\Http\Controllers\Admin\GameListController;
use App\Http\Controllers\Admin\GamePatchController;
use App\Http\Controllers\Admin\GameResourceController;
use App\Http\Controllers\Admin\GameSettingsController;
use App\Http\Controllers\Admin\LinkController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UnverifiedCombosController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ConnectionController;
use App\Http\Controllers\Auth\DiscordAuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\ComboController;
use App\Http\Controllers\ComboListController;
use App\Http\Controllers\ComboVerificationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HoneypotController;
use App\Http\Controllers\ListCanvasComboPickerController;
use App\Http\Controllers\ListCanvasController;
use App\Http\Controllers\ListCanvasEdgeController;
use App\Http\Controllers\ListCanvasNodeController;
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

Route::view('/terms', 'terms')->name('terms');
Route::view('/privacy', 'privacy')->name('privacy');

// Honeypot: a hidden link on every page (see x-layouts.app) that only a
// bot ignoring both CSS and robots.txt would ever follow.
// Throttled because this is the only unauthenticated route that writes a
// row: the limit is set well above what a bot following the link once would
// ever hit, so it bounds table growth without costing us any real data.
Route::get('/t', [HoneypotController::class, 'hit'])->middleware('throttle:60,1')->name('honeypot.hit');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');

    // Second step of password login for a two-factor-enabled account (see
    // AuthController::login / TwoFactorChallengeController). Guest-only:
    // nothing is authenticated yet at this point.
    Route::get('/login/two-factor', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('/login/two-factor', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:5,1')->name('two-factor.challenge.attempt');

    // Sign in / sign up with Discord. Guest-only and behind the admin kill
    // switch. Registration is the first guest-reachable POST in this app that
    // creates rows, hence login's throttle budget rather than the usual 10,1.
    Route::middleware('discord.web')->group(function () {
        Route::post('/auth/discord', [DiscordAuthController::class, 'redirect'])
            ->middleware('throttle:5,1')->name('auth.discord.redirect');
        Route::get('/auth/discord/callback', [DiscordAuthController::class, 'callback'])
            ->middleware('throttle:10,1')->name('auth.discord.callback');
        Route::get('/register/discord', [DiscordAuthController::class, 'showRegister'])
            ->name('auth.discord.register');
        Route::post('/register/discord', [DiscordAuthController::class, 'store'])
            ->middleware('throttle:5,1')->name('auth.discord.register.store');
    });
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::post('/account/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('/account/two-factor', [TwoFactorController::class, 'edit'])->name('two-factor.edit');
    // Same throttle:5,1 budget as the other current-password-taking routes
    // below — enabling and disabling both accept a `current_password` guess.
    Route::post('/account/two-factor', [TwoFactorController::class, 'store'])
        ->middleware('throttle:5,1')->name('two-factor.enable');
    Route::post('/account/two-factor/confirm', [TwoFactorController::class, 'confirm'])
        ->middleware('throttle:5,1')->name('two-factor.confirm');
    Route::post('/account/two-factor/disable', [TwoFactorController::class, 'destroy'])
        ->middleware('throttle:5,1')->name('two-factor.disable');

    Route::get('/account/connections', [ConnectionController::class, 'edit'])->name('connections.edit');
    // The two routes that take a password get login's throttle budget rather
    // than the usual write-route 10,1 — they can be used to test passwords.
    // `discord.web` gates connecting a new account only: with the integration
    // off, someone who already linked must still be able to both see that the
    // link exists (the page above) and remove it (below) — the kill switch is
    // meant to stop new sign-ins, not trap a user into keeping a connection
    // they no longer want.
    Route::post('/account/connections/discord/delete', [ConnectionController::class, 'destroyDiscord'])
        ->middleware('throttle:5,1')->name('connections.discord.destroy');

    Route::middleware('discord.web')->group(function () {
        Route::post('/account/connections/discord', [ConnectionController::class, 'redirectToDiscord'])
            ->middleware('throttle:5,1')->name('connections.discord.redirect');
        Route::get('/account/connections/discord/callback', [ConnectionController::class, 'discordCallback'])
            ->middleware('throttle:10,1')->name('connections.discord.callback');
    });
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard.index')->name('dashboard');

    Route::get('/data-management', [DashboardController::class, 'index'])->name('data-management');
    Route::post('/data-management/destroy', [DashboardController::class, 'destroy'])->middleware('throttle:10,1')->name('data-management.destroy');

    Route::post('/users', [AdminUserController::class, 'store'])->middleware('throttle:10,1')->name('users.store');
    Route::post('/users/{user}/password', [AdminUserController::class, 'updatePassword'])->middleware('throttle:10,1')->name('users.password.update');
    Route::post('/users/{user}/two-factor/disable', [AdminUserController::class, 'disableTwoFactor'])->middleware('throttle:10,1')->name('users.two-factor.destroy');
    Route::post('/users/{user}/moderator', [AdminUserController::class, 'updateModerator'])->middleware('throttle:10,1')->name('users.moderator.update');
    Route::get('/users/{user}/moderated-games', [AdminUserController::class, 'editModeratedGames'])->name('users.moderated-games.edit');
    Route::post('/users/{user}/moderated-games', [AdminUserController::class, 'updateModeratedGames'])->middleware('throttle:10,1')->name('users.moderated-games.update');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingsController::class, 'update'])->middleware('throttle:10,1')->name('settings.update');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

    Route::get('/donation', [DonationController::class, 'edit'])->name('donation.edit');
    Route::put('/donation', [DonationController::class, 'update'])->middleware('throttle:10,1')->name('donation.update');

    Route::get('/external-sites', [ExternalSiteController::class, 'index'])->name('external-sites.index');
    Route::post('/external-sites', [ExternalSiteController::class, 'store'])->middleware('throttle:10,1')->name('external-sites.store');
});

// Shared with moderators: view the user list and toggle a user's trusted
// flag, without the rest of the admin-only dashboard above.
Route::middleware(['auth', 'moderator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users/{user}/trusted', [AdminUserController::class, 'updateTrusted'])->middleware('throttle:10,1')->name('users.trusted.update');
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
    Route::get('/games/{game}/characters/{character}/tabs/flow-chart', [CharacterController::class, 'flowChartTab'])->name('characters.tabs.flow-chart');
    Route::get('/games/{game}/characters/{character}/tabs/flow-chart/next', [CharacterController::class, 'flowChartNext'])->name('characters.tabs.flow-chart.next');
    Route::get('/games/{game}/characters/{character}/tabs/flow-chart/matches', [CharacterController::class, 'flowChartMatches'])->name('characters.tabs.flow-chart.matches');
});

Route::get('/games/{game}/combos', [ComboController::class, 'index'])->name('games.combos.index');
Route::get('/games/{game}/combos/add', [ComboController::class, 'create'])->middleware('auth')->name('games.combos.create');
Route::post('/games/{game}/combos', [ComboController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('games.combos.store');
Route::get('/combos/{combo}', [ComboController::class, 'show'])->name('combos.show');
Route::get('/combos/{combo}/damage-history', [ComboController::class, 'damageHistory'])->name('combos.damage-history');
Route::get('/combos/{combo}/edit', [ComboController::class, 'edit'])->middleware('auth')->name('combos.edit');
Route::post('/combos/{combo}/edit', [ComboController::class, 'update'])->middleware(['auth', 'throttle:10,1'])->name('combos.update');
Route::post('/combos/{combo}/delete', [ComboController::class, 'destroy'])->middleware(['auth', 'throttle:10,1'])->name('combos.destroy');
Route::post('/combos/{combo}/verify', [ComboVerificationController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('combos.verify');
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

    Route::get('/pages/{page}/canvas', [ListCanvasController::class, 'edit'])
        ->withoutMiddleware('throttle:10,1')->name('canvas.edit');

    Route::prefix('pages/{page}/canvas')->name('canvas.')->group(function () {
        Route::get('/combos', [ListCanvasComboPickerController::class, 'search'])
            ->withoutMiddleware('throttle:10,1')->name('combos.search');
        Route::post('/nodes', [ListCanvasNodeController::class, 'store'])->name('nodes.store');
        Route::patch('/nodes/{node}', [ListCanvasNodeController::class, 'update'])->name('nodes.update');
        Route::post('/nodes/{node}/delete', [ListCanvasNodeController::class, 'destroy'])->name('nodes.destroy');
        Route::post('/edges', [ListCanvasEdgeController::class, 'store'])->name('edges.store');
        Route::patch('/edges/{edge}', [ListCanvasEdgeController::class, 'update'])->name('edges.update');
        Route::post('/edges/{edge}/delete', [ListCanvasEdgeController::class, 'destroy'])->name('edges.destroy');
    });
});

Route::get('/tier-lists', [TierListController::class, 'index'])->name('tier-lists.index');
Route::get('/tier-lists/create', [TierListController::class, 'create'])->middleware('auth')->name('tier-lists.create');
Route::post('/tier-lists', [TierListController::class, 'store'])->middleware(['auth', 'throttle:10,1'])->name('tier-lists.store');
Route::get('/tier-lists/{tierList}', [TierListController::class, 'show'])->name('tier-lists.show');
Route::get('/tier-lists/{tierList}/image', [TierListController::class, 'image'])->name('tier-lists.image');

Route::get('/logs', [LogController::class, 'index'])->name('logs.index');

Route::get('/preferences', [PreferenceController::class, 'edit'])->name('preferences.edit');
Route::post('/preferences', [PreferenceController::class, 'update'])->middleware('throttle:20,1')->name('preferences.update');

Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline.index');

Route::get('/challenge', [ChallengeController::class, 'show'])->name('challenge.show');
Route::get('/challenge/tabs/ranking', [ChallengeController::class, 'rankingTab'])->name('challenge.tabs.ranking');
Route::get('/challenge/tabs/calendar', [ChallengeController::class, 'calendarTab'])->name('challenge.tabs.calendar');
Route::get('/challenge/{date}', [ChallengeController::class, 'show'])->where('date', '\d{4}-\d{2}-\d{2}')->name('challenge.show.date');

Route::view('/randomizer', 'randomizer.index')->name('randomizer.index');
Route::view('/randomizer/dbfz', 'randomizer.dbfz')->name('randomizer.dbfz');
Route::view('/randomizer/mvc2', 'randomizer.mvc2')->name('randomizer.mvc2');
Route::view('/randomizer/skullgirls', 'randomizer.skullgirls')->name('randomizer.skullgirls');
Route::view('/randomizer/dengeki', 'randomizer.dengeki')->name('randomizer.dengeki');

Route::view('/input-viewer', 'input-viewer.index')->name('input-viewer.index');

Route::middleware(['auth', 'can:update,game'])->prefix('games/{game}/edit')->name('admin.')->scopeBindings()->group(function () {
    Route::get('/', [GameSettingsController::class, 'edit'])->name('game.edit');
    Route::post('/', [GameSettingsController::class, 'update'])->middleware('throttle:10,1')->name('game.update');

    Route::get('/characters', [AdminCharacterController::class, 'index'])->name('characters.index');
    Route::post('/characters', [AdminCharacterController::class, 'store'])->middleware('throttle:10,1')->name('characters.store');
    Route::post('/characters/bulk', [AdminCharacterController::class, 'bulkUpdate'])->middleware('throttle:10,1')->name('characters.bulkUpdate');

    Route::get('/queries', [CharacterQueryController::class, 'index'])->name('queries.index');
    Route::post('/queries', [CharacterQueryController::class, 'store'])->middleware('throttle:10,1')->name('queries.store');

    Route::get('/patches', [GamePatchController::class, 'index'])->name('patches.index');
    Route::post('/patches', [GamePatchController::class, 'store'])->middleware('throttle:10,1')->name('patches.store');

    Route::get('/links', [LinkController::class, 'index'])->name('links.index');
    Route::post('/links', [LinkController::class, 'store'])->middleware('throttle:10,1')->name('links.store');

    Route::get('/entries', [GameEntryController::class, 'index'])->name('entries.index');
    Route::post('/entries', [GameEntryController::class, 'store'])->middleware('throttle:10,1')->name('entries.store');

    Route::get('/buttons', [ButtonController::class, 'index'])->name('buttons.index');
    Route::post('/buttons', [ButtonController::class, 'store'])->middleware('throttle:10,1')->name('buttons.store');
    Route::post('/buttons/bulk', [ButtonController::class, 'bulkUpdate'])->middleware('throttle:10,1')->name('buttons.bulkUpdate');

    Route::get('/button-aliases', [ButtonAliasController::class, 'index'])->name('button-aliases.index');
    Route::post('/button-aliases', [ButtonAliasController::class, 'store'])->middleware('throttle:10,1')->name('button-aliases.store');
    Route::post('/button-aliases/bulk', [ButtonAliasController::class, 'bulkUpdate'])->middleware('throttle:10,1')->name('button-aliases.bulkUpdate');

    Route::get('/character-button-aliases', [CharacterButtonAliasController::class, 'index'])->name('character-button-aliases.index');
    Route::post('/character-button-aliases', [CharacterButtonAliasController::class, 'store'])->middleware('throttle:10,1')->name('character-button-aliases.store');
    Route::post('/character-button-aliases/bulk', [CharacterButtonAliasController::class, 'bulkUpdate'])->middleware('throttle:10,1')->name('character-button-aliases.bulkUpdate');

    Route::get('/resources', [GameResourceController::class, 'index'])->name('resources.index');
    Route::post('/resources', [GameResourceController::class, 'store'])->middleware('throttle:10,1')->name('resources.store');
    Route::get('/resources/{resource}', [GameResourceController::class, 'values'])->name('resources.values');
    Route::post('/resources/{resource}', [GameResourceController::class, 'storeValue'])->middleware('throttle:10,1')->name('resources.values.store');
    Route::get('/resources/{resource}/aliases', [GameResourceController::class, 'aliases'])->name('resources.aliases');
    Route::post('/resources/{resource}/aliases', [GameResourceController::class, 'storeAliases'])->middleware('throttle:10,1')->name('resources.aliases.store');

    Route::get('/lists', [GameListController::class, 'index'])->name('lists.index');
    Route::post('/lists', [GameListController::class, 'store'])->middleware('throttle:10,1')->name('lists.store');
    Route::post('/lists/bulk', [GameListController::class, 'bulkUpdate'])->middleware('throttle:10,1')->name('lists.bulkUpdate');

    Route::get('/unverified-combos', [UnverifiedCombosController::class, 'index'])->name('unverified-combos.index');
    Route::post('/unverified-combos/verify', [UnverifiedCombosController::class, 'bulkVerify'])->middleware('throttle:10,1')->name('unverified-combos.bulkVerify');
});

// Redirects for URLs the pre-Laravel PHP app (formerly game/, list/) used
// to serve directly, so old bookmarks/search results/external links keep
// resolving instead of 404ing now that those files are gone.
//
// Every legacy id was an integer, so anything else is a crawler or a probe
// rather than a real old bookmark. Checking that here isn't just tidiness:
// route() interpolates the value into the URI pattern, so a value containing
// braces (e.g. ?gameid={{ 7*7 }}) leaves a route parameter unreplaced and
// raises UrlGenerationException — an unauthenticated 500 on a public URL.
// Non-numeric input falls through to the index the same way a missing
// parameter already does.
$legacyId = fn (string $key) => is_numeric(request($key)) ? request($key) : null;

Route::get('/game/index.php', fn () => $legacyId('gameid')
    ? redirect()->route('games.show', $legacyId('gameid'))
    : redirect()->route('games.index'));

Route::get('/game/combo.php', fn () => $legacyId('idcombo')
    ? redirect()->route('combos.show', $legacyId('idcombo'))
    : redirect()->route('games.index'));

Route::get('/game/add.php', fn () => redirect()->route('games.create'));

Route::get('/game/submit.php', fn () => $legacyId('gameid')
    ? redirect()->route('games.combos.index', $legacyId('gameid'))
    : redirect()->route('games.index'));

Route::get('/game/forms.php', fn () => $legacyId('gameid')
    ? redirect()->route('games.combos.create', $legacyId('gameid'))
    : redirect()->route('games.index'));

$legacyGameEditRedirect = fn (string $route) => fn () => $legacyId('gameid')
    ? redirect()->route($route, $legacyId('gameid'))
    : redirect()->route('games.index');

Route::get('/game/edit/game.php', $legacyGameEditRedirect('admin.game.edit'));
Route::get('/game/edit/characters.php', $legacyGameEditRedirect('admin.characters.index'));
Route::get('/game/edit/buttons.php', $legacyGameEditRedirect('admin.buttons.index'));
Route::get('/game/edit/entries.php', $legacyGameEditRedirect('admin.entries.index'));
Route::get('/game/edit/links.php', $legacyGameEditRedirect('admin.links.index'));
Route::get('/game/edit/lists.php', $legacyGameEditRedirect('admin.lists.index'));
Route::get('/game/edit/resources.php', $legacyGameEditRedirect('admin.resources.index'));
Route::get('/game/edit/mass.php', $legacyGameEditRedirect('admin.game.edit'));

Route::get('/list/index.php', fn () => redirect()->route('lists.index'));

Route::get('/list/list.php', fn () => $legacyId('listid')
    ? redirect()->route('lists.show', $legacyId('listid'))
    : redirect()->route('lists.index'));

Route::get('/list/show.php', fn () => $legacyId('id')
    ? redirect()->route('lists.show', $legacyId('id'))
    : redirect()->route('lists.index'));

Route::get('/list/search.php', fn () => redirect()->route('lists.search', request()->only(['gameid', 'q'])));
