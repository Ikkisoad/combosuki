# Security fixes deployment (2026-08-18)

Runbook for applying the following fixes to staging now, and later to production:

1. Iframe injection via a combo's `video` field ([app/Services/VideoEmbedResolver.php](../app/Services/VideoEmbedResolver.php))
2. Game/list/combo passwords stored in plaintext ([app/Services/GamePasswordChecker.php](../app/Services/GamePasswordChecker.php) and related controllers)
3. Missing rate limiting on password-protected endpoints ([routes/web.php](../routes/web.php))
4. Missing security headers ([app/Http/Middleware/SecurityHeaders.php](../app/Http/Middleware/SecurityHeaders.php))

The critical piece is the `2026_08_18_000000_hash_plaintext_passwords` migration, which widens the
`game.globalPass`, `list.password` and `combo.password` columns from `VARCHAR(16)` to `VARCHAR(255)`
and rehashes with bcrypt anything still stored in plaintext. **This is one-way**: `down()` only
reverts the column width, it cannot recover the original plaintext. It is **idempotent** — it skips
any value that already starts with `$2y$`, so it's safe to rerun if it's interrupted partway through.

Validated locally on 2026-08-18 against ~3,100 combo passwords + 67 list passwords + 40 game
passwords: it ran in ~10 minutes (bcrypt cost 12 is deliberately slow) and brought the count of
plaintext passwords to zero.

## Before running in any environment

- [ ] Confirm the code is already committed/merged on that environment's deploy branch
- [ ] Confirm SSH access (avoid running this from a browser-based hPanel terminal — a ~10 minute
      session risks the tab closing before it finishes; if SSH isn't available, use `nohup` +
      `disown` as shown below)
- [ ] **Back up the database before anything else** — the hashing step is irreversible

## Step by step

### 1. Back up the database

```bash
mysqldump -u USER -p DATABASE_NAME > backup_pre_security_fix_$(date +%Y%m%d_%H%M).sql
```

In production, prefer a full snapshot (hPanel usually has automatic database + file backups —
confirm a recent one exists before proceeding, in addition to this manual dump).

### 2. Deploy the code

```bash
cd /path/to/app/laravel
git pull origin <environment-branch>   # stagging for staging; the production branch/tag when it's time
composer install --no-dev --optimize-autoloader
```

No new dependency was added (the migration uses a raw `DB::statement` instead of
`Schema::change()`, so it doesn't need `doctrine/dbal`).

### 3. Maintenance mode

Prevents anyone from attempting a password check mid-migration (rows not yet hashed will fail the
comparison until they're processed).

```bash
php artisan down --secret="pick-a-token-here"
```

Use `--secret` so you can access `/pick-a-token-here` and test before reopening to everyone.

### 4. Run the migration

```bash
nohup php artisan migrate --force > /tmp/migrate_security.log 2>&1 &
disown
tail -f /tmp/migrate_security.log
```

- `--force` is required because `APP_ENV` on staging/production blocks interactive `migrate`.
- `nohup ... & disown` keeps the process running even if the SSH session/terminal drops.
- `Ctrl+C` on `tail -f` only exits the log follow, it doesn't kill the migration.
- If the session really does drop and you're not sure it finished, run `php artisan migrate:status`
  (shows `[3] Ran` once complete) — or just run `migrate --force` again; it's safe since it's
  idempotent.

Time estimate: proportional to the number of rows in `combo`/`list`/`game` with a password set. On
staging that was ~3,200 rows in ~10 min. Before running in production, measure the volume:

```bash
php artisan tinker --execute="
echo DB::table('combo')->whereNotNull('password')->where('password','!=','')->count().' combos'.PHP_EOL;
echo DB::table('list')->whereNotNull('password')->where('password','!=','')->count().' lists'.PHP_EOL;
echo DB::table('game')->whereNotNull('globalPass')->where('globalPass','!=','')->count().' games'.PHP_EOL;
"
```

and estimate `time ≈ (production_rows / staging_rows) × 10min`. If it's substantially larger,
run it during off-peak hours and give users advance notice.

### 5. Clear caches

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### 6. Verify

```bash
php artisan migrate:status | tail -5   # should show hash_plaintext_passwords as Ran

php artisan tinker --execute="
\$col = DB::select('SHOW COLUMNS FROM combo LIKE \'password\'');
echo 'combo.password: '.\$col[0]->Type.PHP_EOL;
echo 'combo still plaintext: '.DB::table('combo')->whereNotNull('password')->where('password','!=','')->where('password','not like','\$2y\$%')->count().PHP_EOL;
echo 'list still plaintext: '.DB::table('list')->whereNotNull('password')->where('password','!=','')->where('password','not like','\$2y\$%')->count().PHP_EOL;
echo 'game still plaintext: '.DB::table('game')->whereNotNull('globalPass')->where('globalPass','!=','')->where('globalPass','not like','\$2y\$%')->count().PHP_EOL;
"
```

All three "still plaintext" counters must be `0`.

### 7. Exit maintenance mode

```bash
php artisan up
```

### 8. Functional test (with the site public again)

- [ ] Edit an existing game/list with the password that already worked before → should keep working
      as-is (the value was rehashed, not changed)
- [ ] Deliberately submit a wrong password 11+ times in a row on the same endpoint → from the 11th
      attempt onward it should return `429 Too Many Requests` (the `throttle:10,1` rate limit)
- [ ] Submit a combo with `video` = `javascript:alert(1)//streamable.com/https` (or any
      non-https/non-whitelisted URL) → the combo page must not render any `<iframe>` with that value
- [ ] `curl -I https://<environment>/` → should include `X-Frame-Options`, `X-Content-Type-Options`,
      `Referrer-Policy`, `Content-Security-Policy: frame-ancestors 'self'` (and
      `Strict-Transport-Security` since it's HTTPS)

## Differences for production

- Data volume is larger → confirm the step 4 time estimate before starting, and pick a low-traffic
  window
- Backup should be production's full backup procedure, not just the manual `mysqldump`
- Running staging first (this runbook) is the dry run: if anything behaves differently than expected
  here, stop and investigate before touching production
- If something goes wrong mid-migration in production and it needs to be aborted: restoring the
  step 1 backup is the only way back to plaintext (the migration itself doesn't undo the hashing);
  reassess before trying again
