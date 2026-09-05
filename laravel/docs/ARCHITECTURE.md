# Architecture & business rules

Reference doc for agents working in this codebase — read this instead of re-exploring the tree. **Keep it current: when a business rule described here changes, update this file in the same change** (see [`../CLAUDE.md`](../CLAUDE.md)).

For account/auth-tier/ownership specifics, see [`user-management.md`](user-management.md) (not repeated here). For the password-hashing migration and related security mechanisms (`SecurityHeaders`, rate limiting), see [`deploy-security-fixes.md`](deploy-security-fixes.md).

## 1. Stack & layout

- Laravel 13, PHP 8.4. Blade templates + Vite-built assets; one plain JS module per feature under `resources/js/` (`comble.js`, `combo-flow-chart.js`, `guide-flow-chart-editor.js`, `input-viewer.js`, `list-manage.js`, `randomizer.js`, `tier-list-maker.js`, `timeline.js`, `challenge-calendar.js`) — no Vue/React/Livewire/Inertia. Bootstrap 5, Cytoscape + cytoscape-edgehandles (the list "canvas"/flow-chart editors), `@discord/embedded-app-sdk` (Discord Activity front end).
- SQLite locally, MySQL in production. This gap has bitten before — see the SQLite-vs-MySQL note in [`../CLAUDE.md`](../CLAUDE.md)'s testing policy (dynamic typing hides bugs that only show up against MySQL's real column types).
- The Laravel app is not the repository root — see [`../CLAUDE.md`](../CLAUDE.md) and [`../README.md`](../README.md) for the root/`comble/`/`laravel/` bridge layout.
- No `routes/api.php` exists. JSON-shaped traffic instead lives in specific `web.php` routes (opted into JSON error rendering per-route — §4) plus the dedicated route files below.

## 2. Route map

Four route files, each with a different middleware posture (wired in `bootstrap/app.php`):

- **`routes/web.php`** — the main app, ordinary session/cookie/CSRF stack. Public browsing (games, characters, combos, lists, tier lists, timeline, randomizer, input viewer); guest-only auth (login, Discord OAuth); authenticated account area (password, 2FA, connected accounts); `admin.*` (site-wide, `admin` middleware) and a separate per-game `admin.*` group under `games/{game}/edit/*` gated by `can:update,game` (moderators of that game, not just any trusted user — see §5); trusted-only routes (`/games/add`, `/users/create`); combo/list/match/tier-list CRUD gated by their policies. Tail of the file 301-redirects the old pre-Laravel PHP app's URLs into the new named routes.
- **`routes/comble.php`** — the Comble daily puzzle web UI. Deliberately registered under the plain `web` middleware group (not `discord.web`/`discord.activity`) because Comble predates and is independent of every Discord feature — see the docblock in `bootstrap/app.php` if you're tempted to fold it into the Discord gates.
- **`routes/activity.php`** — Discord Activity (embedded iframe app) JSON endpoints for Comble (`/token`, `/state`, `/guess`) plus static asset passthroughs. Bearer-token auth (`activity.auth` → `VerifyActivityToken`), registered outside the `web` group — **these routes carry no Laravel session**, which is why the exception-handling rule in §4 special-cases `activity/*`.
- **`routes/discord.php`** — one webhook, `POST /discord/interactions`, signature-verified, driving the bot's slash commands via `App\Services\Discord*` classes.

## 3. Data model

Source of truth is `database/migrations/` — don't enumerate every migration here, but know the table families:

- **Game content**: `game`, `character`, `button`, `button_alias`, `character_button_alias`, `game_resources`/`resources_values` (per-game resource types, e.g. meter gauges), `character_game_resources` (pivot), `character_resource_value_alias`, `link`, `character_link`, `game_alias`, `character_alias`, `game_entry` (combo type/category lookup), `game_patches` (current patch per game), `character_default_queries` (+ pivot to characters — powers damage stats and the daily challenge).
- **Combos**: `combo`, `resources` (per-combo resource usage), `combo_listing` (combo↔list pivot), `combo_damage_histories` (one row per combo+patch, see §7).
- **Lists/guides**: `list`, `list_category` (+ nullable `filters` JSON and `query_limit` tinyint, 1-3, set/cleared together — a saved combo-search filter set, same shape/mechanism as `character_default_queries` below, that feeds the category with up to `query_limit` matching combos alongside any manually-tagged ones; only usable when the guide's `game_idgame` is set), `list_page` (+ `page_type`), `list_page_canvas_node` / `list_page_canvas_edge` (the visual flow-chart-style guide pages).
- **Tier lists**: `tier_list`, `tier_list_entry`.
- **Matches**: `matches`, `match_resources` (character-vs-character records).
- **Comble puzzle**: `comble_daily_picks`, `comble_attempts`, `comble_day_views`.
- **Daily Challenge**: `daily_challenge_picks` (see §6 — distinct from Comble).
- **Auth/admin**: `user` (see naming quirk below), `user_connected_account` (Discord link), `game_moderator` (pivot), `site_setting`, `donation_progress`, `external_site`, `edit_histories` (generic audit log), `bot_hits` (honeypot hits), `discord_command_usages`.

**Naming quirk**: Laravel's default `users`/`cache`/`jobs` migrations exist unused alongside the app's real, singular `user` table (created by a separate app migration). Don't confuse the two — the app's `User` model points at `user`.

**Key models** (`app/Models`) and non-obvious relationships:

| Model | Notes |
|---|---|
| `Game` | `hasManyThrough` combos via characters; `belongsToMany` moderators (`User` via `game_moderator`) |
| `Character` | belongs to `Game`; has combos, aliases, links, button/resource-value aliases; `belongsToMany` gameResources, defaultQueries |
| `Combo` | belongs to character/patch/user/listingType(`GameEntry`)/verifier(`User`); has resources, damageHistories; `belongsToMany` lists via `combo_listing`. See §6/§7 for its model-event side effects. |
| `ListModel` / `ListPage` / `ListPageCanvasNode`/`Edge` | a list has pages; a page can be `isCanvas()` (visual node/edge editor) or a plain content page |
| `User` | helpers `isTrusted()`, `isModerator()`, `hasUsablePassword()`, `hasTwoFactorEnabled()` — see [`user-management.md`](user-management.md) |
| `GameMatch` (table `matches`) | belongs to game + two characters + up to two users |
| `TierList` / `TierListEntry` | belongs to game/user; entries ordered by tier |
| `CombleDailyPick` / `CombleAttempt` / `CombleDayView` | the puzzle game — separate from Daily Challenge, see §6 |
| `DailyChallengePick` | the daily challenge's frozen `(query, character)` pick, see §6 |
| `ComboDamageHistory` | per-combo, per-patch damage snapshot, see §7 |
| `EditHistory` | generic cross-content audit log |

## 4. Exception handling — JSON is opt-in, 403s redirect

All in `bootstrap/app.php`'s `withExceptions()` (no `app/Exceptions/Handler.php` — this app uses Laravel 11+'s bootstrap-based config; `app/Exceptions/` only holds two small custom exception classes).

- **`shouldRenderJsonWhen`**: JSON error responses are only rendered for `api/*` (unused today), `discord/*`, `activity/*` path prefixes, or an explicit named-route allowlist (AJAX endpoints that live on otherwise-HTML pages: list bulk/reassign endpoints, the canvas node/edge CRUD endpoints, `comble.guess`/`comble.guess.date`). Every route not in that list gets Laravel's default HTML error page.
- **Non-JSON 403s redirect + flash**: if the exception is a 403, the request doesn't expect JSON, and the route isn't under `activity/*`, the handler returns `redirect()->to(url()->previous(route('home')))->with('error', "You don't have permission to do that.")` instead of an error page. The `activity/*` exclusion exists because those routes carry no Laravel session (§2) — a session-based redirect there would itself throw; no route under `activity/*` aborts 403 today, but the guard is there for when one does.
- **Adding a new AJAX-on-an-HTML-page endpoint?** Add its route name to the `shouldRenderJsonWhen` allowlist, or its JS will silently receive a redirect/HTML error page instead of the JSON it expects.

## 5. Authorization tiers

Four tiers, each strictly containing the ones before it: regular user < `trusted_user` < `is_moderator` < `is_admin` — plus a fifth, orthogonal dimension: per-game moderator (`game_moderator` pivot), which is **not** implied by `is_moderator`/`trusted_user`.

- Middleware (`app/Http/Middleware/EnsureUserIs{Admin,Moderator,Trusted}`, aliased `admin`/`moderator`/`trusted` in `bootstrap/app.php`) gate whole route groups.
- Policies gate individual records:
  - `ComboPolicy`/`ListPolicy`/`MatchPolicy` — same shape: `update`/`delete` pass for `isTrusted()` (trusted/moderator/admin) **or** the record's own author. `ComboPolicy::verify` is trusted-only.
  - `GamePolicy::update` — admin **or** a user specifically assigned as moderator of *that* game via `game_moderator`. **No bypass for a plain trusted user** — being globally trusted doesn't let you edit an arbitrary game's content unless you're also that game's moderator (or an admin). `GameController::store()` auto-attaches the creator as a moderator of their own new game so this doesn't lock them out immediately.
  - `GamePolicy::delete` — admin only, full stop, even for the game's own creator.
- **Double-gate subtlety**: `Admin/UnverifiedCombosController::bulkVerify()`'s route only requires `can:update,game` (a per-game moderator), but each combo inside is re-checked against `Gate::allows('verify', $combo)` (global `isTrusted()`). A moderator assigned to only one game can see the unverified queue but may successfully verify zero of them if they aren't also globally trusted.
- Combo/list ownership and ambient ownerless legacy rows: see [`user-management.md`](user-management.md).
- **Validation**: there's no `app/Rules` — no custom `ValidationRule` classes exist. Rules live directly in `FormRequest::rules()`. `StoreComboRequest::rulesFor()` is exposed as a **static** method specifically so the Discord `/csk submit` wizard validates against the exact same rule set as the web form instead of duplicating it — if you add a rule there, both surfaces get it for free; if you refactor it into an instance method, the bot loses validation parity silently.

## 6. Challenges vs. Trials vs. Comble — three distinct features, don't conflate them

- **Daily Challenge** (`App\Services\DailyChallenge`, `ChallengeController`, table `daily_challenge_picks`): each calendar day (in `DailyGameClock`'s timezone) gets a `(CharacterQuery, Character)` pair.
  - **Eligibility**: a `CharacterQuery` only enters the pool starting the calendar day *after* it was created (`DailyChallenge::earliestDate()`/`pickPair()`), so a query added today can't retroactively become "today's" challenge for a day already served.
  - **Pick-then-freeze**: the pair for a date is deterministic — `sha256(date) mod eligible-pair-count` — computed once, the first time that date is ever requested, then persisted forever in `daily_challenge_picks` (race-safe via the table's unique `day` index + catching the `QueryException`). This means: (a) the pair for a past date never changes even if the picking algorithm is later rewritten, and (b) a character added after a date was first served can never retroactively become eligible for it.
  - **The "winning" combo is NOT frozen** — unlike the pair, the top-damage matching combo for a pair is re-searched live via `FiltersCombos::searchCombos()` every time, so it changes whenever a matching combo is added/edited/deleted (this is exactly what invalidates `ChallengeStatsCache` — see §7).
  - `ChallengeController::computeRankings()` ranks users by how many days their combo ended up as the pair's top result; guest submissions (no `user_iduser`) are excluded.
- **Trials** (`InputViewerTrialController`, the Input Viewer's "Trials" tab): an unauthenticated, read-only practice widget — search guides, list a guide's combos, fetch one combo's ordered move breakdown (`ComboFlowChartBuilder::movesForCombo()`) to drive a live input-practice UI. No submission, no scoring, no ownership gate of any kind (this is deliberate, per the controller's own docblock).
- **Comble** (`App\Services\Comble*`, `CombleController`, tables `comble_daily_picks`/`comble_attempts`/`comble_day_views`): a separate Wordle-style "guess the day's combo" puzzle, also exposed as a Discord Activity. `CombleGuessEvaluator::starterMatch()` compares only the **first 6 characters** of the combo string with spaces stripped from both sides first — confirming combo-notation spacing (§8) is a display convention a player isn't expected to reproduce exactly.

## 7. Damage stats & challenge stats caching

Both caches are **event-driven off `Combo::booted()`** (`static::saved`/`static::deleted` hooks) — there is no scheduled job or queued recompute (no relevant `app/Console`/`app/Jobs` entries).

- **`App\Support\DamageStatsCache`** (backs `GameController::damageStatsTab()`, per-game per-character average damage across all `CharacterQuery`s): cached **forever**, key `games.{gameId}.damage-stats.{public|trusted}`, `forget($gameId)` clears both tiers. Invalidated on every `Combo` save/delete for that combo's game.
- **`App\Support\ChallengeStatsCache`** (backs `ChallengeController::rankingTab()`/`calendarTab()`): rather than per-entry invalidation, every key embeds a **version number** (`challenge.cache-version`) that `Combo::booted()` bumps on *every* combo write — because production's cache driver is `file` (shared hosting, no tag support), a single version bump invalidates every cached ranking/calendar entry at once instead of trying to compute which specific cached days a given combo write could affect. Day boundaries are handled by folding the date into the key, not by active expiry.
- **Both are split into `trusted`/`public` variants**, not one shared entry — because the underlying search (`FiltersCombos::searchCombos()`, `Combo::scopeVisibleTo`/`scopeVisibleToPublic`) is visibility-scoped (trusted staff see unverified combos too). A single shared entry would leak whichever tier triggered the last recompute onto every other visitor regardless of their own tier.
- **Serialization gotcha that shapes both caches' design**: cached values must be plain arrays/scalars, never Eloquent models/Collections — the `file` cache driver's `serialize()`/`unserialize()` is fragile across deploys (autoloader class-map changes between requests can produce "incomplete object" `unserialize()` failures). `GameController::computeDamageStats()` caches raw `character_id` ints and rehydrates `Character` models afterward in `hydrateDamageStats()`; `ChallengeController` does the analogous thing with `User` ids. **If you add something to either cache, cache the id, not the model.**
- **Per-combo damage history**: `Combo::booted()`'s first `saved` hook maintains one `ComboDamageHistory` row per `(combo, patch)` — editing damage while keeping the same patch corrects that patch's row in place; editing damage *and* bumping the patch dropdown inserts a new row and leaves the previous patch's value untouched (lets a combo page show how a character's damage changed across patches/nerfs). Fires on create too, via `wasRecentlyCreated` (a fresh insert never populates `wasChanged()`).

## 8. Combo notation — tokenizing, rendering, and search

Core logic in `App\Services\ComboNotationRenderer`. **None of this validates input** — `StoreComboRequest`/`UpdateComboRequest` only require `combo` to be a non-empty string; everything below is display/search normalization applied *after* the fact, not a format the submitter is held to.

- **Tokenizing is whitespace-based** (`tokenize()`): the notation is split on literal spaces only — there is no button-boundary parser. Each word is matched against the game's configured `Button` rows using each button's `match_type` (`exact`/`contains`/`starts_with`/`ends_with`); among matches, the first one whose `color` isn't the default placeholder (`#ffffff`) wins — a button still at the default color is treated as "not really color-coded," so the word renders as plain text even if it technically matched.
- **Rendering-spacing rule**: the space between two adjacent tokens is **dropped whenever exactly one of them is color-coded** (an uncoded modifier glues onto its colored neighbor — `"5 LK"` renders `"5LK"`); a space is **kept** between two colored tokens (distinct moves) and between two uncoded tokens. This is the rule behind the "no space between button tokens, ` > ` (spaced) only between chained moves" convention.
- **Color propagation**: an uncoded token glued to a colored neighbor takes that neighbor's color too (so the whole glued unit reads as one move) — it prefers the *following* colored token first (a motion input leads into the button after it), falling back to the preceding one if there's no next token.
- **Alias resolution** (`resolveAliases()`/`resolvedAliases()`): expands game-wide `ButtonAlias` and character-specific `CharacterButtonAlias` entries (e.g. "Throw" → "5LP") case-insensitively, **longest-alias-first** so a short alias can't clobber a longer one it's a substring of; character aliases take priority over a game alias using the same word.
- **Move-boundary splitting** (`ComboFlowChartBuilder::moveTokens()`, used by flow charts and Trials): a move boundary is either an ignored button (e.g. the `>` chain separator, dropped) or the start of a second color-coded button within the same run. If a game never color-codes any button, it falls back to one move per `>`-separated segment — color-coding is a rendering nicety, not something move-detection depends on.
- **Search normalization mirrors the same rules**: `FiltersCombos::applyFilters()` builds a nested SQL `REPLACE()` chain over the `combo` column (aliases expanded innermost, then ignored tokens stripped) so a search pattern normalizes the same way stored notation does. Nesting is capped at **`MAX_NESTED_REPLACEMENTS = 15`** (`FiltersCombos.php`) because SQLite's parser stack overflows past ~31 nested levels; past the cap, the *shortest* (least specific) aliases are dropped first rather than failing the search outright.
- **Saved filter sets** (`FiltersCombos::buildFiltersFromRequest()`) turn a submitted filter form into the same flat filter map `applyFilters()`/`searchCombos()` consume, so a filter set can be stored once and re-run live later instead of only ever running against the current request. Two features save one: a game's default queries (`CharacterQuery::$filters`, §3/§6/§7, always capped to the top 1 match) and a guide category's query (`ListCategory::$filters` + `$query_limit`, §3, creator-configurable up to 3) — `ListController::categoriesAndGroupedCombos()` merges up to `query_limit` of a category's query matches in alongside its manually-tagged combos (deduped by combo id) when rendering the public guide page, but *not* on the management hub's combo board, since every card there is a real `combo_listing` pivot row that drag-and-drop/removal act on and a query match isn't.

## 9. Services layer index (`app/Services`)

- **Combo/notation**: `ComboNotationRenderer` (§8), `ComboFlowChartBuilder` (§8, §6), `ComboSubmissionService`, `VideoEmbedResolver` (iframe/video-embed handling, XSS-guarded per `deploy-security-fixes.md`).
- **Challenge**: `DailyChallenge` (§6).
- **Tier lists**: `TierListAggregator`, `TierListImageRenderer` (renders the shareable tier-list image).
- **Users**: `NicknamePolicy` (see `user-management.md`), plus 2FA logic referenced from `Auth/TwoFactorController`.
- **Stats**: `UserStats` (profile page stats).
- **Comble**: `CombleAttemptRecorder`, `CombleDailyCombo`, `CombleDiscordProgress`, `CombleGuessEvaluator` (§6), `CombleRevealer`, `CombleStats`.
- **Discord bot/Activity**: `DiscordAccountLinker`, `DiscordChallenge`, `DiscordCharacterPage`, `DiscordCombleGame`, `DiscordComboSearch`, `DiscordComboSubmit`, `DiscordComboWizard` (shares `StoreComboRequest::rulesFor()`, §5), `DiscordGuideBrowse`, `DiscordGuideSearch`, `DiscordTierListImage`.

Cache helpers live in `app/Support` (`DamageStatsCache`, `ChallengeStatsCache`, `DailyGameClock`), not `app/Services`.
