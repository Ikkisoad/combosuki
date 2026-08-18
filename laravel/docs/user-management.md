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

Use this to fix a user that was created incorrectly (e.g. via raw SQL) too — updating
through Eloquent rehashes it correctly in place:

```bash
php artisan tinker --execute="\App\Models\User::where('nickname', 'someone')->update(['password' => 'a-new-password']);"
```

## Promote/demote a site admin

```bash
php artisan tinker --execute="\App\Models\User::where('nickname', 'someone')->update(['is_admin' => true]);"
```

`is_admin` isn't enforced on any route yet — it's the foundation for the admin mass-edit
tool (see the "let's block anonymous submissions" plan/commit).
