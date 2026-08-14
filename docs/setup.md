# Local Development Setup

## Prerequisites

- **PHP 8.3+** (developed against 8.4.24) with the usual Laravel extensions
  (`pdo_sqlite` and/or `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`
  or `imagick` for medialibrary thumbnails)
- **Composer 2**
- **Node.js 20+** and npm
- **SQLite3** (bundled with PHP's `pdo_sqlite` on most installs) for local
  dev/tests — MySQL 8+ only needed if you want to develop against it
  directly instead of SQLite (see [deployment.md](deployment.md) for the
  production MySQL setup)

This project was built using [Laravel Herd](https://herd.laravel.com/) on
Windows, but nothing is Herd-specific — `php artisan serve` works
identically.

## Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Default `.env.example` is already configured for zero-setup local dev:
`DB_CONNECTION=sqlite`, `SESSION_DRIVER=database`, `MAIL_MAILER=log`,
`QUEUE_CONNECTION=database`. Create the SQLite file and migrate:

```bash
touch database/database.sqlite   # Windows: New-Item database/database.sqlite -ItemType File
php artisan migrate --seed
```

This runs every migration in [database.md](database.md#migration-order) and
all seeders, producing:
- Super Admin: `superadmin@example.com` / `password`
- School Admin (Riverside Demo School): `admin@riverside-demo.test` / `password`
- A full demo academic year, sections, subjects, and a handful of demo students

Start the API:

```bash
php artisan serve   # http://localhost:8000
```

If you're using Herd instead, the site is served automatically at its Herd
URL — update `APP_URL` and the frontend's `VITE_API_URL` to match instead
of running `artisan serve`.

### Stripe (optional — needed for Phase 6 billing features)

The app runs fine without this; `STRIPE_KEY`/`STRIPE_SECRET` left blank
just means Cashier calls (Checkout, plan changes) will error if invoked.
To exercise the SaaS billing layer locally:

1. Create a free [Stripe](https://dashboard.stripe.com/register) account
   and switch to **test mode** — no business verification needed.
2. Copy the test-mode Publishable/Secret keys into `.env` as `STRIPE_KEY`/
   `STRIPE_SECRET`.
3. Install the [Stripe CLI](https://docs.stripe.com/stripe-cli) and run
   `stripe listen --forward-to http://localhost:8000/stripe/webhook` —
   it prints a `whsec_...` value, set that as `STRIPE_WEBHOOK_SECRET`
   (this is a per-session secret, different from the one shown in the
   Stripe dashboard for a production webhook endpoint).
4. Use test card `4242 4242 4242 4242`, any future expiry, any CVC, for
   Checkout.

See [roadmap.md § Status](roadmap.md#status) for what Phase 6 has wired up
so far, and `App\Http\Controllers\Webhooks\StripeWebhookController` for
the webhook handling itself.

## Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev   # http://localhost:5173
```

`.env.example` already points `VITE_API_URL` at `http://localhost:8000/api`
— update it if your backend runs somewhere else (a Herd URL, a different
port).

## Verifying the setup

1. Open `http://localhost:5173`, log in as `admin@riverside-demo.test` /
   `password`.
2. You should land on a role-appropriate Dashboard with a full sidebar
   (Overview, People, Academics, Administration sections) — if the sidebar
   only shows "Dashboard" and nothing else, `permissions` didn't come back
   on the login response; see the gotcha in
   [rbac.md § Frontend permission checks](rbac.md#frontend-permission-checks).
3. Visit Students → you should see the demo students seeded by
   `DemoStudentSeeder`.

## Running both together during development

Two terminals, backend on 8000 and frontend on 5173, is the standard setup
this project was developed and tested against. There's no proxy/concurrently
script wiring them together — CORS + Sanctum's stateful-domain list is what
lets the separately-served SPA authenticate against the API (see
`FRONTEND_URLS` / `SANCTUM_STATEFUL_DOMAINS` in `backend/.env`).

## Common issues

- **Vite binds IPv6-only by default on some setups** — if `curl localhost:5173`
  works but `curl 127.0.0.1:5173` doesn't (or vice versa), that's expected;
  use whichever the browser resolves consistently, or set `server.host` in
  `vite.config.ts` explicitly.
- **Port already in use** — a previous `npm run dev`/`php artisan serve`
  didn't shut down cleanly. Find and kill the process holding the port
  rather than changing ports, so `.env` URLs stay valid.
- **`SQLSTATE[HY000]: General error: 1 no such table`** — migrations
  haven't run against the SQLite file, or `database/database.sqlite`
  doesn't exist yet; re-run the `touch` + `migrate` steps above.
- **Login succeeds but every permission check fails / sidebar is empty** —
  see the `UserResource.permissions` gotcha in [rbac.md](rbac.md); this was
  a real bug once, not hypothetical.
- **Every file upload (student import, question import, marks import, any
  future one) 422s with `{"errors":{"file":["The file failed to upload."]}}`,
  and the raw response sometimes shows `PHP Request Startup: File upload
  error - unable to create a temporary file` before Laravel's JSON even
  starts** — this is a genuine `php artisan serve`-on-Windows issue, not an
  application bug, and it's easy to lose hours chasing it as one (it did,
  once). Root cause: `Illuminate\Foundation\Console\ServeCommand` spawns its
  actual request-handling subprocess via Symfony `Process` with an
  explicitly reconstructed environment — its `$passthroughVariables`
  allowlist (`APP_ENV`, `PATH`, `SYSTEMROOT`, a few `HERD_*`/`XDEBUG_*`
  vars) does **not** include `TMP`/`TEMP`, so the spawned worker loses them
  entirely. Without them, PHP's temp-directory resolution for multipart
  uploads falls back to `C:\WINDOWS` itself (not even `C:\WINDOWS\Temp`),
  which a non-admin account can't write to — so *every* multipart upload
  fails, regardless of file size or which endpoint. Confirmed via a raw
  `curl -F` POST reproducing the identical error with zero application
  code involved, and confirmed fixed by setting `upload_tmp_dir` explicitly
  in `php.ini` (Herd's per-version ini, e.g.
  `C:\Users\<you>\.config\herd\bin\php84\php.ini`) — an explicit ini value
  bypasses the broken environment-variable resolution chain entirely, since
  it's consulted before any `TMP`/`TEMP` fallback. **This setting lives
  outside the repo** (a machine-level PHP config file, not `.env` or
  anything Git tracks) — anyone hitting this on a fresh Windows setup needs
  to make the same change themselves; there's no way to fix it from inside
  the codebase. If restarting `artisan serve` after this doesn't seem to
  help, check for orphaned worker processes first (`Get-Process php` on
  PowerShell) — `PHP_CLI_SERVER_WORKERS` pre-forks multiple workers that
  persist independently of the outer `artisan serve` process, so a plain
  `pkill -f "artisan serve"`-style kill (matching only the outer command
  line) can leave old, still-listening, pre-fix workers answering requests
  on the same port while looking like a fresh restart succeeded.

## Running the app end-to-end without a browser

For headless verification (CI, or driving the app without opening a
browser window), `chromium-cli` or a standalone Playwright script against
the two dev servers above is the pattern used during this project's own
manual smoke testing — see [testing.md § Manual/browser smoke testing](testing.md#manualbrowser-smoke-testing).
