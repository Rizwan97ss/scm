# Deployment

This covers taking the Phase 0-3 slice from local SQLite dev to a
production-style deployment. Nothing here is phase-specific — it applies
as-is to every later phase built on top.

## 1. Switch to MySQL

Local dev uses SQLite for zero-setup convenience; migrations use no
SQLite-only syntax, so the cutover is configuration-only:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=school_management_system
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

Create the database (`CREATE DATABASE school_management_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`),
then:

```bash
php artisan migrate --force
php artisan db:seed --force   # only for a fresh install with demo/starter data —
                               # skip or write a production-specific seeder for a
                               # real school's initial rollout
```

`--force` is required because Laravel blocks destructive commands by
default when `APP_ENV=production`.

**Gotcha found doing this cutover for real:** two migrations
(`create_online_test_attempts_table`, `create_student_transport_assignments_table`)
had multi-column unique/index definitions with no explicit name.
Laravel's auto-generated name (`{table}_{col1}_{col2}_{col3}_{unique|index}`)
came out over MySQL's 64-character identifier limit for both — SQLite has
no such limit, so this was invisible through Phase 0-12's entire SQLite-only
development and 248-test suite. Both now pass an explicit short name as the
second argument to `->unique()`/`->index()`. If a *future* migration adds a
multi-column index/unique constraint on a long table/column combination and
skips the explicit name, it'll hit the same wall the first time it's
migrated against MySQL, not before — worth naming these explicitly by habit
rather than waiting for it to fail.

Also: MySQL's `CREATE TABLE`/`ALTER TABLE` are **not transactional** the
way Laravel's migration wrapping implies — if a migration's `up()` fails
partway through (exactly what happened here: the `CREATE TABLE` succeeded,
the following `ALTER TABLE ... ADD UNIQUE` didn't), the already-run
statements stay committed even though Laravel doesn't mark the migration
as ran. A retry then fails again with "table already exists." Recovery is
manual: inspect what the failed migration actually left behind
(`SHOW COLUMNS FROM the_table`, check row count) and drop it before
re-running `migrate` — don't assume a failed migration left nothing behind
on MySQL the way it would on SQLite/Postgres.

## 2. Environment hardening

- `APP_ENV=production`, `APP_DEBUG=false` — never run production with debug
  mode on; it leaks stack traces and env values in error responses.
- `APP_URL` set to the real backend origin.
- `SESSION_ENCRYPT=true` if not already.
- `SESSION_DOMAIN` set explicitly if frontend and backend share a parent
  domain (needed for the session cookie to be visible cross-subdomain).
- `SANCTUM_STATEFUL_DOMAINS` / `FRONTEND_URLS` updated to the real
  production frontend origin(s) — this is the actual CSRF/session security
  boundary, get it exact (no wildcards).
- Serve over HTTPS only — Sanctum's session cookie should be `Secure` in
  production (Laravel sets this automatically when the request is HTTPS
  and `SESSION_SECURE_COOKIE` isn't forced off).
- `BCRYPT_ROUNDS` — 12 (current default) is reasonable; don't lower it.

## 3. Frontend build

```bash
cd frontend
npm run build     # outputs to dist/, type-checks as part of the build
```

`VITE_API_URL` must point at the real backend origin at build time (Vite
env vars are baked in at build, not read at runtime) — set it in the CI/
build environment, not just a local `.env`. Serve `dist/` as static files
behind any web server (nginx, Caddy, a CDN); it's a pure SPA with
client-side routing, so the server needs a fallback that serves
`index.html` for any unmatched path (React Router handles the rest
client-side).

## 4. Reverse proxy topology

Two supported shapes:

- **Same origin** (recommended — simplest CORS/cookie story): reverse
  proxy routes `/api/*` and `/sanctum/*` to the Laravel app, everything
  else to the built SPA's `dist/`. `VITE_API_URL` becomes a relative
  `/api`, no CORS needed at all.
- **Separate subdomains** (`app.example.com` frontend, `api.example.com`
  backend): requires `FRONTEND_URLS`/`SANCTUM_STATEFUL_DOMAINS` to list the
  frontend's real origin, and `SESSION_DOMAIN=.example.com` so the session
  cookie is visible to both subdomains.

Either shape puts a reverse proxy (nginx, Caddy, a load balancer) in
front of Laravel, which means the app sees the proxy's own connection,
not the real client's — set `TRUSTED_PROXIES` (`backend/.env`) or
`Request::ip()`, HTTPS detection, and the `Strict-Transport-Security`
header (see [architecture.md § Security posture](architecture.md#security-posture))
all silently reflect the proxy, not the client:

```env
# The proxy's own IP if it's a separate host, or '*' if Laravel only ever
# receives traffic from a proxy on the same trusted network (e.g. nginx
# on the same box/container). Never '*' if the app is reachable directly
# from the internet on top of that — that would let any client spoof
# X-Forwarded-* headers.
TRUSTED_PROXIES=*
```

## 5. Queue worker

`QUEUE_CONNECTION=database` locally; for production, either keep the
database driver (fine at this scale — no module built so far dispatches
jobs yet) or switch to Redis/SQS when a later phase adds real background
work (bulk import processing at scale, scheduled report generation,
notification fan-out). Run a worker process regardless once queued jobs
exist:

```bash
php artisan queue:work --tries=3
```

Use a process supervisor (systemd, Supervisor, or your platform's
equivalent) — `queue:work` exiting on a deploy or crash should restart it,
not silently stop processing.

## 6. Scheduled jobs

No scheduled command exists yet as of Phase 0-3 (the academic-year
`is_current` flag is toggled explicitly via the `activate` endpoint, not on
a timer). When a later phase adds one (e.g. auto-transitioning
`upcoming` → `active` academic years, nightly attendance-summary rollups),
register it in `routes/console.php` and point cron at:

```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

## 7. File storage

`FILESYSTEM_DISK=local` by default — fine for a single-server deployment.
For anything horizontally scaled or where uploaded files (student
documents, avatars) need to survive a redeploy/be shared across instances,
switch to S3-compatible storage:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...
```

`spatie/laravel-medialibrary` (used for all student/staff documents and
avatars) respects the configured disk with no code changes — it's already
disk-agnostic.

## 8. Mail

`MAIL_MAILER=log` locally (emails write to the log instead of sending —
used for password resets, portal invites, verification emails). Set a real
transactional provider for production:

```env
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="noreply@yourschool.example"
MAIL_FROM_NAME="${APP_NAME}"
```

## 9. Backups

At minimum: automated daily MySQL dumps (`mysqldump` or your host's managed
backup feature) retained for a rolling window, plus backup of the file
storage disk (local or S3) if student documents live there. Enrollment
history and audit logs (see [database.md](database.md),
[architecture.md § Security posture](architecture.md#security-posture))
are the records a school is least able to reconstruct from anywhere else —
prioritize their durability specifically, not just "the database" in general.

## 10. Multi-school considerations

The system is architecturally multi-school-ready (see
[architecture.md § Multi-tenancy](architecture.md#multi-tenancy)) but
Phase 0-3 has been seeded and tested with one demo school. Onboarding a
second real school onto the same deployment needs no schema/code change —
create a `School` row (Super Admin → Schools), seed its own
`RolePermissionSeeder`-equivalent role set if it needs custom roles beyond
the defaults, and set any school-specific `settings` overrides (branding,
admission number format). There's no per-school database/schema split by
design — isolation is enforced by `SchoolScope`, not infrastructure, which
is what keeps onboarding a new school a data operation rather than a
deploy.

## 11. Stripe billing (Phase 6)

Local dev uses **test-mode** keys and the Stripe CLI (`stripe listen
--forward-to http://localhost:8000/stripe/webhook`) to forward webhook
events to a machine Stripe can't reach directly. None of that applies in
production — Stripe can reach a real public URL directly:

- Switch `STRIPE_KEY`/`STRIPE_SECRET` to **live-mode** keys
  (`dashboard.stripe.com/apikeys`, no `/test/` prefix).
- Create the real plan tiers as live-mode Products/Prices
  (`dashboard.stripe.com/products` — test-mode and live-mode objects are
  entirely separate, the `price_.../prod_...` IDs seeded locally do not
  carry over) and update the `plans` table's `stripe_product_id`/
  `stripe_price_id` columns to match (via the platform admin console or
  directly).
- Register a real webhook endpoint in the Stripe dashboard
  (`dashboard.stripe.com/webhooks`) pointing at
  `https://yourdomain.com/stripe/webhook` (or the `api.` subdomain, per
  whichever topology § 4 uses) — **do not run `stripe listen` in
  production**, it's a local-forwarding dev tool only. Copy that
  endpoint's own signing secret into `STRIPE_WEBHOOK_SECRET`; it is
  different from the one `stripe listen` prints locally.
- The webhook route is registered outside `/api/v1` on purpose (see the
  comment above it in `routes/web.php` and `bootstrap/app.php`'s
  `validateCsrfTokens(except: ['stripe/*'])`) — make sure the reverse
  proxy config from § 4 doesn't accidentally exclude `/stripe/*` when
  routing `/api/*` to the backend.
- `Cashier::useCustomerModel(School::class)` and `Cashier::ignoreRoutes()`
  (`AppServiceProvider::boot()`) mean Cashier's own auto-registered
  webhook route is inert — only `StripeWebhookController` (which also
  syncs `schools.billing_status`) ever runs.

## Pre-deploy checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Real `APP_KEY` generated and kept secret (not the dev one)
- [ ] MySQL configured, migrated, seeded appropriately for the real rollout (not the demo seeder)
- [ ] HTTPS enforced, `SANCTUM_STATEFUL_DOMAINS`/`FRONTEND_URLS` set to real origins only
- [ ] `VITE_API_URL` baked into the frontend build for the real backend origin
- [ ] Queue worker running under a supervisor, if any queued jobs exist
- [ ] Mail transport configured (not `log`)
- [ ] File storage disk decided (local vs. S3) based on deployment topology
- [ ] Backup schedule in place before real student data enters the system
- [ ] `php artisan test` and `npm run build` both clean on the exact commit being deployed
- [ ] `TRUSTED_PROXIES` set to the reverse proxy's IP (or `*` only if it's
      unreachable directly from the internet) — see § 4
- [ ] Stripe switched to live-mode keys, live Products/Prices created, a
      real webhook endpoint registered (not `stripe listen`) — see § 11
- [ ] `composer audit` and `npm audit` both clean on the exact commit being
      deployed — dependency CVEs surface after code is written, so this is
      a deploy-time check, not a one-time pass
- [ ] Version control in place (this project has been built without one so
      far — initialize a git repo and commit before the first real
      deploy, so a bad release can actually be rolled back)
