# combosuki

Community-fueled searchable database of fighting-game combos (see [README.md](README.md) for the full pitch and local setup steps).

## Where things actually live

This directory is a thin bridge, not the app:

- [`index.php`](index.php) / [`.htaccess`](.htaccess) — forwards requests into `laravel/`.
- [`comble/`](comble) — a second front-controller bridge, for the `comble.*` subdomain (a Discord Activity / puzzle-game front end).
- [`laravel/`](laravel) — **the actual Laravel application.** All code, routes, models, views, migrations, and tests live here.

**Before changing anything inside `laravel/`, read [`laravel/CLAUDE.md`](laravel/CLAUDE.md) and [`laravel/docs/ARCHITECTURE.md`](laravel/docs/ARCHITECTURE.md).** They cover conventions, the data model, and business rules (combo notation, the daily challenge, caching, auth tiers, exception handling) that aren't obvious from a quick read of the code.
