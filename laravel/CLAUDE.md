# Testing policy

- Every new feature (controller action, service method, policy rule, form request rule) must ship with a test in the same change: `tests/Unit` for pure/isolated logic, `tests/Feature` for anything touching Eloquent, HTTP, or the database.
- Whenever existing business logic changes, review and update the test(s) that cover it in the same change — don't leave assertions stale.

## Conventions

- PHPUnit 12, classic `test_*` methods extending `Tests\TestCase` — no Pest.
- Fixtures are built with direct `Model::create([...])` calls, not factories (`database/factories` is intentionally unused).
- `tests/Unit` for logic that needs no database (build models in memory with `forceFill()`/`setRelation()` when a relation is needed without a real query). `tests/Feature` (with `Illuminate\Foundation\Testing\RefreshDatabase`) for anything that runs a real Eloquent query or hits a route.
- Tests run against SQLite in-memory (`phpunit.xml`), while production runs MySQL. SQLite's dynamic typing can hide bugs that only appear on MySQL's real column types (e.g. PDO returning a `BIGINT UNSIGNED` id as a string vs an `INT` as a native int). When comparing values that originate from differently-typed MySQL columns, guard against this with a dedicated Unit test that builds mismatched-type models via `forceFill()` — see `tests/Unit/CombleGuessEvaluatorTest.php` for the pattern.
