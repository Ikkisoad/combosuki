# Creating and managing users

There is no sign-up page — accounts are created manually. Login is by `nickname` +
`password`, not email.

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
