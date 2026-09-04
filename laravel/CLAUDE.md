# combosuki (Laravel app)

A fighting-game combo database: users browse games/characters, submit and search combo notation, curate guide "lists," and hit a few side features (daily challenge, a Wordle-style "Comble" puzzle, tier lists, a Discord bot/Activity). Laravel 13 / PHP 8.4, Blade + Vite + per-feature vanilla JS modules (no Vue/React/Livewire/Inertia), SQLite locally / MySQL in production.

**Read [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) before making non-trivial changes** — it covers the route map, data model, and business rules (combo-notation rendering, the daily challenge's pick-then-freeze algorithm, event-driven damage/challenge-stat caching, the auth-tier/policy model, and the opt-in-JSON exception-rendering rule) that aren't obvious from a quick read of the code.

**When a business rule changes** (a new auth tier, a new caching strategy, a new notation rule, a new route-group pattern, etc.), **update `docs/ARCHITECTURE.md` in the same change** — the same discipline as the testing policy below, applied to documentation instead of tests.

# Testing policy

- Every new feature (controller action, service method, policy rule, form request rule) must ship with a test in the same change: `tests/Unit` for pure/isolated logic, `tests/Feature` for anything touching Eloquent, HTTP, or the database.
- Whenever existing business logic changes, review and update the test(s) that cover it in the same change — don't leave assertions stale.

## Conventions

- PHPUnit 12, classic `test_*` methods extending `Tests\TestCase` — no Pest.
- Fixtures are built with direct `Model::create([...])` calls, not factories (`database/factories` is intentionally unused).
- `tests/Unit` for logic that needs no database (build models in memory with `forceFill()`/`setRelation()` when a relation is needed without a real query). `tests/Feature` (with `Illuminate\Foundation\Testing\RefreshDatabase`) for anything that runs a real Eloquent query or hits a route.
- Tests run against SQLite in-memory (`phpunit.xml`), while production runs MySQL. SQLite's dynamic typing can hide bugs that only appear on MySQL's real column types (e.g. PDO returning a `BIGINT UNSIGNED` id as a string vs an `INT` as a native int). When comparing values that originate from differently-typed MySQL columns, guard against this with a dedicated Unit test that builds mismatched-type models via `forceFill()` — see `tests/Unit/CombleGuessEvaluatorTest.php` for the pattern.
