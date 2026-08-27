# Creating and managing users

Accounts are created three ways: **self-service sign-up with Discord**, by a trusted user
via `/users/create`, or manually via tinker. Login is by `nickname` + `password`, or with
Discord — never by email, since there is no email column.

## Discord accounts have no password

Signing up with Discord creates an account with `password = null`. Such an account:

- **cannot log in with a password.** `AuthController::login` refuses it explicitly, using
  the same generic "Incorrect nickname or password." as any other failure, so the login
  form doesn't become a "which accounts are Discord-only?" oracle.
- **can set a first password itself** at `/account/password`, which drops the
  current-password field when there is nothing to confirm. That branch is chosen from the
  database, never from request input.
- **cannot disconnect Discord** until it has a password — doing so would remove the only
  way into an account with no email and no password reset.

An admin can still set a password for anyone from `/admin/users`.

The whole web-facing integration (sign-in, sign-up and connecting) can be switched off at
`/admin/settings`. **Turning it off locks out every account whose only credential is
Discord.** The Discord bot is unaffected by that flag.

Nicknames chosen through Discord sign-up are validated more strictly than the ones trusted
users create: 3-45 characters, `A-Z a-z 0-9 _ . -` only, no reserved staff names, and
uniqueness compared case-insensitively (see `App\Services\NicknamePolicy`). The restricted
character set is what makes homoglyph impersonation impossible — `nickname` is both the
login identifier and the public display name shown beside the Admin/Trusted badges.

**Always go through `php artisan tinker` (or other Eloquent code), never a raw SQL
`INSERT`/`UPDATE`.** `App\Models\User::$password` has a `hashed` cast, which bcrypts
whatever plain string you assign the moment you `create()`/`update()` through Eloquent.
A raw SQL statement bypasses the cast entirely and stores the value as-is — logging in
with that account then fails with `This password does not use the Bcrypt algorithm.`,
because Laravel's hasher refuses to verify a value that isn't a bcrypt hash.

## Create a new user

```bash
php artisan tinker --execute="\App\Models\User::create(['nickname' => 'someone', 'password' => 'a-password', 'is_admin' => false]);"
```

Pass `'is_admin' => true` to make them a site admin.

## Change an existing user's password

Use this to fix a user that was created incorrectly (e.g. via raw SQL) too. You must
load a model instance and call `save()` on it — `User::where(...)->update([...])` is a
query builder mass update that bypasses the `hashed` cast and stores the password as
plaintext, reproducing the same "This password does not use the Bcrypt algorithm" bug:

```bash
php artisan tinker --execute="\$u = \App\Models\User::where('nickname', 'someone')->firstOrFail(); \$u->password = 'a-new-password'; \$u->save();"
```

## Promote/demote a site admin

```bash
php artisan tinker --execute="\App\Models\User::where('nickname', 'someone')->update(['is_admin' => true]);"
```

Admins can reach `/admin` and `/admin/users` (gated by the `admin` middleware), including
creating new users with any combination of `is_admin`/`trusted_user`, resetting
passwords, and toggling a user's trusted status.

## Trusted users

`trusted_user` is a third tier between a regular logged-in user and an admin, gated by
the `trusted` middleware (`User::isTrusted()` is true for `trusted_user` or `is_admin`).
Trusted users can:

- create and edit games and their content — characters, buttons, links, entries,
  options/resources, and game-scoped lists (the `/games/{game}/edit/*` routes, plus
  `/games/add`).
- edit or delete *any* combo or list, not just their own (see below).
- create new plain user accounts via `/users/create` — these are always created with
  `is_admin`/`trusted_user` both off, regardless of what's posted; only an admin can
  promote someone from there.

Only an admin can grant or revoke `trusted_user` on an existing account (the "Trust" /
"Revoke" button on `/admin/users`), or via tinker:

```bash
php artisan tinker --execute="\App\Models\User::where('nickname', 'someone')->update(['trusted_user' => true]);"
```

## Combo/list ownership

Regular (untrusted) users can submit combos and lists, and edit or delete only the ones
they created (`Combo`/`ListModel` `user_iduser` matching the logged-in user). Trusted
users and admins bypass this check and can edit/delete anyone's. This is enforced by
`App\Policies\ComboPolicy` and `App\Policies\ListPolicy`. Combos/lists with no owner
(`user_iduser` is null — legacy rows) can only be touched by trusted users/admins.
