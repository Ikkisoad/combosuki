# combosuki

Community-fueled searchable environment that shares and perfects combos for fighting games. Players browse games, characters, and move lists, then publish and refine combo routes with other users. Registered users can submit and edit combos, curate personal lists, and (for admins) manage the underlying game data — characters, buttons, resources, links, and entries.

## Project layout

The app runs on Laravel and lives in [`laravel/`](laravel). The repository root only holds a thin front controller that bridges hosting paths to the Laravel app:

- [`index.php`](index.php) / [`.htaccess`](.htaccess) — forward all requests to `laravel/public`-equivalent bootstrapping so the app can be served from the domain root without moving the Laravel install.
- [`comble/`](comble) — a second front-controller bridge for the `comble.*` subdomain (see [`comble/index.php`](comble/index.php)).
- [`laravel/`](laravel) — the actual Laravel application (routes, controllers, views, migrations, etc.).

The pre-Laravel PHP app that used to live at the repository root (`game/`, `list/`, `matches/`, `server/`, plus root-level `css/`, `js/`, `img/`) has been removed; its URLs now redirect into the Laravel app (see the bottom of [`laravel/routes/web.php`](laravel/routes/web.php)).

### Key areas inside `laravel/`

- `app/Http/Controllers` — game browsing, combos, lists, timeline, auth, and an `Admin/` namespace for game/content management.
- `app/Models` — `Game`, `Character`, `Combo`, `List`, `Button`, `Link`, `GameResource`, `User`, etc.
- `resources/views` — Blade templates (combos, games, lists, admin dashboard, auth).
- `database/migrations` — schema for games, characters, combos, lists, resources, and auth/admin fields.
- `routes/web.php` — all application routes, including `admin.*` routes gated by `auth` + `admin` middleware.

## Requirements

- PHP 8.3+
- Composer
- Node.js + npm (for building/serving front-end assets via Vite)
- A database — SQLite (default for local dev) or MySQL

## Running it locally

1. **Clone and enter the Laravel app directory** — all commands below are run from `laravel/`:

```bash
cd laravel
```

2. **Install PHP dependencies**

```bash
composer install
```

3. **Install JS dependencies**

```bash
npm install
```

4. **Create your environment file**

```bash
cp .env.example .env
```

By default `DB_CONNECTION` is `sqlite`. To use SQLite, create the database file:

```bash
touch database/database.sqlite
```

To use MySQL instead, edit `.env` and set `DB_CONNECTION=mysql` plus `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` to match your local database.

5. **Generate the application key**

```bash
php artisan key:generate
```

6. **Run database migrations**

```bash
php artisan migrate
```

7. **Build front-end assets**

```bash
npm run build
```

Or, for local development with hot reloading:

```bash
npm run dev
```

8. **Start the app**

The simplest option is Laravel's built-in dev script, which runs the PHP server, queue listener, log tailer, and Vite dev server together:

```bash
composer run dev
```

The app will be available at `http://localhost:8000`.

Alternatively, if you're serving the project through a local web server stack (e.g. WAMP) pointed at the repository root, the root-level `index.php`/`.htaccess` bridge will route requests into the Laravel app automatically — just make sure your virtual host document root is the repository root (not `laravel/public`).

### Running tests

```bash
php artisan test
```
