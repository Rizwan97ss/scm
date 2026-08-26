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

Create the **landlord** database (`CREATE DATABASE school_management_system
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`), then:

```bash
php artisan migrate --force
php artisan db:seed --force   # only for a fresh install with demo/starter data —
                               # skip or write a production-specific seeder for a
                               # real school's initial rollout
```

`--force` is required because Laravel blocks destructive commands by
default when `APP_ENV=production`.

**The MySQL user needs `CREATE DATABASE`/`DROP DATABASE` privileges, not
just DML on one schema.** Since Sub-phase C-G's database-per-tenant
conversion, provisioning a school (self-service signup or the platform
console) physically creates a new database and migrates it synchronously,
in-request (`stancl/tenancy`'s job pipeline, `shouldBeQueued(false)` — see
[architecture.md § Multi-tenancy](architecture.md#multi-tenancy)). The
landlord connection's DB user is what stancl uses to create/migrate/drop
these tenant databases, so it needs full DDL rights on the MySQL server,
not just the landlord schema:

```sql
GRANT ALL PRIVILEGES ON *.* TO 'your_db_user'@'%';
-- or, narrower — two grants, both needed:
GRANT CREATE, DROP, ALTER, INDEX ON *.* TO 'your_db_user'@'%';
GRANT ALL PRIVILEGES ON `tenant%`.* TO 'your_db_user'@'%';
FLUSH PRIVILEGES;
```

The db-pattern wildcard here is a plain SQL `LIKE`-style `%`, matched
against the tenant database's actual name — `config('tenancy.database.prefix')`
(`tenant`) + the tenant's numeric key + `config('tenancy.database.suffix')`
(empty), e.g. `tenant7`. There's no underscore in that name, so an escaped
`` `tenant\_%` `` pattern (matches only `tenant_anything`) silently grants
nothing — `CREATE DATABASE` can still succeed off the global `CREATE`
grant, but every subsequent read/write inside the new tenant database
(migrations, role seeding, the admin user) then fails with a `SELECT
command denied` / `access violation: 1142` error, because nothing actually
matched. This is an easy mistake to make and easy to miss, since the first
grant statement still reports `Query OK`.

A user scoped to only the landlord schema will provision a `School` row
successfully (that write goes through fine) and then fail the moment
`CreateDatabase`/`MigrateDatabase` runs — visible as a 500 on signup, with
the compensating rollback (`SchoolProvisioningService::provision()`'s
catch block) tearing down the now-orphaned landlord row and its
half-created tenant database. That rollback only covers failures *inside*
the try block, though — a failure that happens while the `School` row
itself is being inserted (which is exactly when the tenant database
creation fires, via `School::$dispatchesEvents`) needs the model
constructed and saved inside the try, not via a bare `::create()` call
outside it, or the row leaks with no cleanup attempt at all.

**Don't reset this database user's password through a hosting panel's UI**
(CloudPanel, cPanel, etc.) once these grants are in place — several panels
implement "reset password" by recreating the user account outright, which
silently drops every grant beyond the panel's own default (access to just
that one site's database) and reintroduces this exact failure. If the
password needs to change, do it directly in MySQL instead, which changes
only the password:

```sql
ALTER USER 'your_db_user'@'%' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;
```

If a panel-based reset does happen, the grants above need to be reapplied
from scratch — check current state first with `SHOW GRANTS FOR
'your_db_user'@'%';`.

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

**Phase 15 encryption backfill — deploy order matters here.** Several PII
columns (`users.phone`, `students.medical_info`/
`emergency_contact_phone`/`address_line1`/`address_line2`,
`guardians.national_id`/`address_line1`/`address_line2`,
`payments.reference_number`) now carry Laravel's `encrypted` cast. Adding
the cast to a model does nothing to rows already stored as plaintext — the
next read throws `DecryptException` on every one of them. The correct
order for every environment that has existing data (i.e. anything past a
fresh install) is:

1. `php artisan tenants:migrate` (or `migrate --force` against each tenant)
   — applies `2026_08_12_090001_widen_pii_columns_for_encryption.php`,
   widening the target columns to `text` (the encrypted envelope runs
   ~3-4x longer than plaintext, and these were `string(255)`).
2. `php artisan security:encrypt-pii` (add `--dry-run` first to preview,
   `--school=<slug>` to scope to one tenant) — rewrites existing plaintext
   values in place via the same encrypter the model cast uses. Idempotent
   and safe to re-run.
3. Only then deploy the application code carrying the `encrypted` casts.

A rolling deploy that serves old (plaintext-expecting) and new
(encrypted-expecting) code against the same rows at the same time
corrupts data — don't skip step 2 or reorder it after step 3.

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

Database-per-tenant means every school's URL is its own subdomain
(`{slug}.example.com`) — this isn't optional the way "same origin vs.
separate subdomains" used to be a style choice; the reverse proxy **must**
route wildcard subdomains, both for the API and (unless the frontend is
served some other way) the SPA.

- **Wildcard DNS**: an `A`/`ALIAS` record for `*.example.com` (and
  `example.com` itself, for the central/signup/platform domain) pointing
  at the reverse proxy. Locally this is `*.localtest.me`, a public DNS
  service that resolves any subdomain to `127.0.0.1` with zero setup —
  there is no production equivalent of that convenience; a real wildcard
  DNS record is required.
- **Wildcard TLS certificate**: a single cert for `*.example.com` (plus
  `example.com`) covers every current and future tenant subdomain without
  reissuing per school. Let's Encrypt issues wildcard certs via DNS-01
  challenge only (HTTP-01 can't prove control of a wildcard) — automate
  renewal through your DNS provider's ACME plugin (certbot's DNS
  plugins, Caddy's built-in ACME + DNS provider module, etc.), since
  manual DNS-01 renewal every ~90 days doesn't scale past the first
  renewal.
- **Same-origin-per-tenant** (recommended — matches how local dev already
  behaves, and how `App\Http\Client...`/`src/api/client.ts` are written):
  reverse proxy routes `{slug}.example.com/api/*` and `.../sanctum/*` to
  Laravel, `{slug}.example.com/*` (everything else) to the built SPA,
  **preserving the Host header** so `tenancy.subdomain` resolves the
  right tenant. `VITE_API_URL` becomes a relative `/api` (see `src/api/
  client.ts`'s `resolveApiUrl()` — a relative value opts out of its
  runtime-hostname-substitution logic and resolves same-origin instead),
  no CORS needed at all. The central/platform domain (`example.com`, no
  subdomain) needs the same routing for the signup and platform-login
  pages, just with no tenant to resolve.
- **Separate API subdomain per environment** (`app.example.com` frontend
  domain pattern, `api.example.com` backend) is possible but loses the
  "no CORS" simplicity entirely: `FRONTEND_URLS`/`TENANCY_FRONTEND_DOMAIN_PATTERN`
  need to list/match the frontend's real wildcard origin, and
  `SESSION_DOMAIN` still needs to stay unset/exact-host (**not**
  `.example.com`) — a shared parent-domain session cookie would defeat
  the whole point of subdomain-based tenant isolation (see
  [architecture.md § Authentication](architecture.md#authentication)).
  Only reach for this shape if the same-origin-per-tenant proxy routing
  above is genuinely not achievable in your infrastructure.

**`TENANCY_FRONTEND_DOMAIN_PATTERN` gotcha**: this value is a regex
starting with `#` (used as the PCRE delimiter). In a `.env` file, an
*unquoted* value starting with `#` is silently treated as a comment and
discarded entirely — this shipped broken in local dev for a while before
a live browser test caught it (every tenant-subdomain request failing
CORS with no `allowed_origins_patterns` configured at all). Always
single-quote it: `TENANCY_FRONTEND_DOMAIN_PATTERN='#^https://([a-z0-9-]+\.)?example\.com$#'`
— double quotes also break, differently (the pattern's own backslash
escapes get interpreted as PHP-style escape sequences and fail to parse).

Any of these shapes puts a reverse proxy (nginx, Caddy, a load balancer)
in front of Laravel, which means the app sees the proxy's own connection,
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

`QUEUE_CONNECTION=database` locally (already runs alongside `php artisan
serve` in the local `composer dev` script via `queue:listen`). **As of
Phase 15, this is no longer optional** — `App\Jobs\GenerateDataExportJob`
(data export generation, `docs/api.md`'s "Data export" section) is a real
queued job now, and a school's export requests silently never complete
without a worker actually processing them. Keep the database driver (fine
at this scale) or switch to Redis/SQS if background work grows heavier.
Run a worker process in production:

```bash
php artisan queue:work --tries=3
```

Use a process supervisor (systemd, Supervisor, or your platform's
equivalent) — `queue:work` exiting on a deploy or crash should restart it,
not silently stop processing.

## 6. Scheduled jobs

**As of Phase 15, three scheduled commands exist** (`routes/console.php`)
— all three fan out per-tenant themselves (`School::all()->each(fn ($school)
=> $school->run(...))`, the same pattern used elsewhere in this codebase),
so no per-school cron configuration is needed, just the one line below:

```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

- `retention:clean-activity-logs` (daily) — prunes each tenant's activity
  log per its own `retention.activity_log_days` setting.
- `retention:clean-expired-exports` (hourly) — deletes data-export ZIPs
  (and their `DataExport` rows) past `expires_at`.
- `retention:anonymize-stale-accounts` (daily) — off by default per
  tenant (`retention.inactive_account_anonymize_days` is null unless a
  school explicitly opts in via Settings).

**Gotcha already worked around, not left for you to hit**: Spatie's own
`activitylog:clean` command reads one *global* config value and has no
concept of per-tenant databases — a bare `Schedule::command('activitylog:
clean')` would run against whatever tenant happens to be "current" when
the scheduler ticks (none) and silently no-op forever. `retention:clean-
activity-logs` wraps it correctly per-tenant instead; don't add a second,
naive schedule entry calling `activitylog:clean` directly.

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

## 10. Multi-school considerations (database-per-tenant)

Onboarding a new school is a **write**, not a deploy — self-service
signup or the platform console creating a `School` row triggers the same
provisioning pipeline either way (`SchoolProvisioningService::provision()`):
a new physical database, migrated, seeded with default roles, no manual
step required. But "no per-school database split" is no longer true the
way it was pre-conversion — the opposite is the whole point now, and it
has real operational consequences at scale:

- **Backups are N databases, not one.** § 9 above still applies to the
  landlord database (schools/plans/subscriptions — small, changes
  rarely), but every tenant database needs its own backup coverage too.
  A single `mysqldump` of "the database" no longer captures student/
  academic/financial data at all — script backups to enumerate and dump
  every `tenant*` (or however tenant databases are named in your MySQL
  instance) database, not just the landlord one.
- **Connection overhead scales with tenant count.** Each request that
  touches a tenant (i.e. almost all of them) opens a connection to that
  tenant's specific database. At meaningfully large tenant counts, watch
  MySQL's `max_connections` and consider connection pooling (ProxySQL,
  RDS Proxy, etc.) — this app doesn't do anything unusual here, but the
  N+1-databases shape means it's worth checking sooner than a
  single-database app would need to.
- **The platform console's cross-tenant reads are an accepted N+1 cost,
  not yet optimized.** `PlatformSchoolController`/`School::studentCount()`/
  `staffCount()` open a real connection per school when listing usage
  across many tenants at once — fine at today's tenant counts, a known
  future optimization (a landlord-side rollup counter each tenant writes
  on change, instead of a live cross-database count) if the schools list
  ever needs to scale past what that N+1 pattern comfortably handles.
- **A broken/missing tenant database degrades, doesn't crash.**
  `School::studentCount()`/`staffCount()` check `databaseExists()` before
  ever switching into a tenant's connection, so one school with a
  missing or drifted database shows as zero usage in the platform console
  instead of 500ing the whole schools list. Worth knowing if you ever see
  a school reporting all-zero usage unexpectedly — check whether its
  tenant database actually exists before assuming the data itself is
  wrong.
- **New role defaults roll out per-tenant, not globally.** Changing
  `SchoolProvisioningService::SCHOOL_SCOPED_ROLE_PERMISSIONS` (the default
  role/permission matrix) only affects schools provisioned *after* the
  change — existing tenants' already-seeded roles aren't retroactively
  updated. Run `php artisan permissions:rollout` once per deploy that
  changes the matrix (add `--school=<slug>` to scope to one tenant) —
  idempotent, safe to re-run, iterates every school and calls
  `seedDefaultRoles()` again.

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
- [ ] Landlord MySQL database configured and migrated (`php artisan
      migrate --force`); the DB user has `CREATE`/`DROP DATABASE` rights
      for tenant provisioning, not just DML on the landlord schema — see § 1
- [ ] Wildcard DNS (`*.example.com`) and a wildcard TLS certificate in
      place and auto-renewing — see § 4
- [ ] Reverse proxy routes wildcard subdomains, preserving the Host
      header, to both the API and the SPA — see § 4
- [ ] `TENANCY_FRONTEND_DOMAIN_PATTERN` single-quoted in `.env` (it starts
      with `#`, silently discarded as a comment if unquoted) and its regex
      actually matches the real production frontend origin pattern — see § 4
- [ ] `SESSION_DOMAIN` left unset/exact-host, **not** a shared parent
      domain — a shared-domain session cookie defeats subdomain-based
      tenant isolation, see [architecture.md § Authentication](architecture.md#authentication)
- [ ] HTTPS enforced, `SANCTUM_STATEFUL_DOMAINS`/`FRONTEND_URLS` set to
      real wildcard origins only
- [ ] `VITE_API_URL` baked into the frontend build for the real backend
      origin (or left relative, `/api`, if using the same-origin-per-
      tenant proxy shape — see § 4)
- [ ] Queue worker running under a supervisor — no longer optional as of
      Phase 15, see § 5
- [ ] Mail transport configured (not `log`)
- [ ] File storage disk decided (local vs. S3) based on deployment topology
- [ ] Backup schedule covers **every tenant database**, not just the
      landlord one — see § 10
- [ ] `php artisan test` and `npm run build` both clean on the exact commit being deployed
- [ ] `TRUSTED_PROXIES` set to the reverse proxy's IP (or `*` only if it's
      unreachable directly from the internet) — see § 4
- [ ] Stripe switched to live-mode keys, live Products/Prices created, a
      real webhook endpoint registered (not `stripe listen`) — see § 11
- [ ] `composer audit` and `npm audit` both clean on the exact commit being
      deployed — dependency CVEs surface after code is written, so this is
      a deploy-time check, not a one-time pass
- [ ] If this deploy is the one introducing/changing encrypted PII columns:
      migrate → `php artisan security:encrypt-pii` → deploy code, in that
      exact order — see § 1's "Phase 15 encryption backfill" note
- [ ] A live browser pass through signup → Stripe Checkout redirect →
      tenant login on a real subdomain → platform login, on the actual
      production domain — not just `php artisan test`/`npm run build`
      passing. The subdomain/CORS/cookie-scoping interactions this
      architecture depends on are exactly the class of bug neither catches
      (see this project's own history: a broken `TENANCY_FRONTEND_DOMAIN_PATTERN`
      shipped past both for a while before a live pass caught it).
