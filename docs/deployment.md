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
-- or, narrower: GRANT CREATE, DROP, ALTER, ... ON `tenant\_%`.* TO ...
-- if your MySQL setup can express a prefix-scoped grant safely.
```

A user scoped to only the landlord schema will provision a `School` row
successfully (that write goes through fine) and then fail the moment
`CreateDatabase`/`MigrateDatabase` runs — visible as a 502 on signup, with
the compensating rollback (`SchoolProvisioningService::provision()`'s
catch block) cleaning up the now-orphaned landlord row.

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
  updated. A permission-matrix change that needs to reach existing
  schools needs its own one-off script iterating `School::all()` and
  calling `$school->run(fn () => ...)`, the same pattern
  `SchoolProvisioningService::seedDefaultRoles()` itself uses.

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
- [ ] Queue worker running under a supervisor, if any queued jobs exist
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
- [ ] A live browser pass through signup → Stripe Checkout redirect →
      tenant login on a real subdomain → platform login, on the actual
      production domain — not just `php artisan test`/`npm run build`
      passing. The subdomain/CORS/cookie-scoping interactions this
      architecture depends on are exactly the class of bug neither catches
      (see this project's own history: a broken `TENANCY_FRONTEND_DOMAIN_PATTERN`
      shipped past both for a while before a live pass caught it).
