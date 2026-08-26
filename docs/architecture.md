# Architecture

## Overview

```
scm/
  backend/   Laravel 13 API (PHP 8.4) — /api/v1/*, session-based auth via Sanctum
  frontend/  React 19 + TypeScript SPA (Vite) — consumes the API, no server rendering
  docs/      This documentation set
```

The two apps are independently deployable. In dev they run on separate ports
(`localhost:8000` backend, `localhost:5173` frontend) and talk over HTTP with
cookies; in production they can be served from the same origin (SPA behind a
reverse proxy that forwards `/api` to Laravel) or different subdomains, as
long as CORS and Sanctum's stateful-domain list agree — see
[deployment.md](deployment.md).

## Backend layering

Each module (Users, Roles, Settings, AcademicYear, Student, ...) follows the
same shape, so once you've read one you've read them all:

```
Route (routes/api.php)
  → Controller (thin: validate via Form Request, delegate, wrap in ApiResponse)
    → Form Request (authorization delegated to Policy, validation rules)
    → Policy (per-model, decides view/create/update/delete/... per user+school)
    → Service (only where there's real logic beyond a save — id generation,
       enrollment transitions, dashboard aggregation, settings caching)
    → Model (Eloquent, BelongsToSchool trait where tenant-scoped)
  → API Resource (shapes the JSON response)
→ ApiResponse::success()/created()/error() envelope
```

Controllers stay thin on purpose: a controller method's job is "validate,
authorize, call one thing, return a Resource." Anything with real branching
logic (student ID generation, promotion/transfer/withdrawal rules, dashboard
role-context switching) lives in `app/Services/`, unit-testable without HTTP.

Ten of the simpler CRUD entities (departments, grade levels, rooms, subjects,
etc.) share a generic `App\Http\Controllers\Api\V1\CrudController` base for
`index`/`show`/`destroy`, with `store`/`update` overridden per entity where
validation differs. This isn't a framework — it's boilerplate reduction for
genuinely identical CRUD shapes; anything with distinct behavior (Student,
User, Role, Settings) has its own full controller.

### Response envelope

Every API response — success or error — is wrapped by `App\Support\ApiResponse`:

```json
{ "success": true, "message": "...", "data": { ... }, "meta": { ... } }
{ "success": false, "message": "...", "errors": { "field": ["..."] } }
```

`ApiResponse::noContent()` still returns HTTP 200 with `data: null` (not 204)
so the frontend's response interceptor has one shape to handle universally.

## Multi-tenancy

**Database-per-tenant**, not row-level. Every school has its own, physically
separate database — there is no shared `school_id` column, trait, or global
scope left anywhere in the tenant schema (that was the pre-Sub-phase-F
architecture; the whole application was converted off it, not incrementally
migrated). Isolation is structural: a query issued against one tenant's
connection is incapable of returning another tenant's rows, because that
data lives in a different physical database.

- **Landlord (central) database** — holds exactly what genuinely is
  cross-tenant: `schools`, `plans`, Cashier's `subscriptions`/
  `subscription_items`, `platform_users`, and Laravel's own
  infrastructure tables (`cache`, `jobs`, `migrations`, ...). Nothing
  student/staff/academic ever lives here.
- **Tenant databases** — everything else (users, students, exams,
  invoices, roles/permissions, settings, ...). One physical MySQL/SQLite
  database per school, auto-created and migrated by `stancl/tenancy`'s job
  pipeline the moment a `School` row is created (`School::$dispatchesEvents`
  maps Eloquent's own create/update events onto stancl's tenant lifecycle
  events — see `app/Models/School.php`'s docblock).
- **`App\Models\School`** is the tenant model (stancl's "predefined
  columns" mode — its own real columns, not the package's default JSON
  blob) and uses `CentralConnection` to pin `School::query()` to the
  landlord database regardless of which tenant is currently active.
  `Plan` and `PlatformUser` do the same, for the same reason: without it,
  a query against a landlord-only model would silently follow whatever
  tenant connection happened to be active — a bug that shipped once (see
  `App\Services\SubscriptionService::swapPlan()`'s and
  `App\Http\Controllers\Api\V1\BillingController`'s history) before being
  caught by a live browser test, not the test suite.
- **Tenant identification is subdomain-based**: `{school-slug}.{central
  domain}` (`riverside-demo.localtest.me` locally). `App\Tenancy\
  SlugTenantResolver` resolves the subdomain directly against
  `School.slug` — there's no separate `domains` table. The
  `tenancy.subdomain` middleware (wrapping every tenant-facing route in
  `routes/api.php`) resolves the tenant and swaps the active database
  connection before the route runs; requests that don't match a real
  subdomain get a clean 404, not an exception (`TenancyServiceProvider::
  boot()`'s `InitializeTenancyBySubdomain::$onFail`).
- **`tenant()` helper**: returns the currently-resolved `School` (or
  `null` outside a tenant context). Since every model instance lives in
  exactly one tenant's database, "the current tenant" and "this record's
  school" are the same thing by construction — this replaced the old
  `$model->school` relation everywhere.
- **Cross-tenant reads** (the platform console listing every school's
  usage) go through `School::run(fn () => ...)` (stancl's `TenantRun`
  trait) to explicitly step into one tenant's connection, run a query,
  and step back out — see `School::studentCount()`/`staffCount()` for the
  pattern, including the defensive `databaseExists()` check a broken/
  orphaned tenant row requires (its own docblock explains why).

New tenant-scoped tables just need a normal migration under
`database/migrations/tenant/` — no `school_id` column, no scope, no
trait. A model only needs special handling if it's genuinely landlord-
level (shared across every tenant), in which case give it
`CentralConnection` like `School`/`Plan`/`PlatformUser`.

## RBAC

`spatie/laravel-permission`, **teams disabled** (`'teams' => false` in
`config/permission.php`). Teams existed to scope one role table across
many schools sharing a database — with one physical database per school,
that partitioning is redundant: the table itself is already scoped.

- **Roles/permissions live in the tenant database**, seeded per-tenant by
  `App\Services\SchoolProvisioningService::seedDefaultRoles()` (self-
  service signup and the `PermissionSeeder` it also runs are the same
  code path — see the class's own docblock).
- **Super Admin is not a tenant role.** It's `App\Models\Platform\
  PlatformUser` — a landlord-connection-only model with no
  roles/permissions of its own at all. Being a `PlatformUser` implies
  full platform access via a `Gate::before` check in
  `AppServiceProvider::boot()` (type-checked against
  `instanceof PlatformUser`, not a role string).
- **Full detail:** see [rbac.md](rbac.md) for the permission catalogue,
  default role matrix, and how to add a new role or module. (Note: as of
  this pass rbac.md itself still describes the old teams-based model in
  places — verify against `config/permission.php` and
  `SchoolProvisioningService` before relying on it for team/teams-related
  specifics.)

## Authentication

**Two entirely separate guards, sessions, and frontend auth contexts** —
a tenant User and a PlatformUser are never the same kind of session, and
neither can authenticate the other's routes.

**Tenant auth** (`auth:sanctum`, tenant-zone routes only — see
`routes/api.php`'s TENANT zone, guarded by `tenancy.subdomain`):

1. Frontend calls `GET /sanctum/csrf-cookie` before the first mutating
   request (`ensureCsrfCookie()` in `src/api/client.ts`), which sets the
   `XSRF-TOKEN` cookie.
2. `POST /api/v1/auth/login` queries the *current tenant's* `users` table
   only (email/username uniqueness is therefore per-tenant, not global —
   a real, desirable side effect of the conversion), verifies via
   `Hash::check()`, and calls `Auth::login()`.
3. Every subsequent request rides the session cookie; `SESSION_DOMAIN` is
   deliberately `null` (exact-host cookies), so a session set on one
   tenant subdomain is never sent to another — this is itself part of the
   isolation story, not just cookie hygiene.
4. `AuthContext` (frontend) re-fetches via `GET /api/v1/auth/me` on app
   boot. Both this probe and `ThemeContext`'s `/settings/public` probe are
   tenant-zone-only routes mounted globally in the SPA, so they legitimately
   404 (not just 401) on any central-domain page (login, signup, the
   platform console) — both are marked `meta: { silentError: true }` in
   their `useQuery` calls for exactly that reason.

**Platform auth** (`auth:platform`, central-domain routes — `POST /api/v1/
auth/platform-login`, `GET .../platform-me`, `POST .../platform-logout`):
a completely separate session guard (`config/auth.php`'s `platform` guard
+ `platform_users` provider) for `PlatformUser`. The frontend's
`PlatformAuthContext`/`PlatformLoginPage`/`PlatformProtectedRoute`/
`PlatformShell` mirror the tenant auth stack but never share state with
it — see `src/context/PlatformAuthContext.tsx`.

**Signup is a three-request handoff**, not a single login, because of the
subdomain/cookie boundary above:

1. `POST /api/v1/auth/signup` (central domain, no tenant resolved yet)
   provisions the school + admin synchronously and starts a Stripe
   Checkout session. It does **not** log the admin in — a session cookie
   set on the central domain can't carry over to the new tenant's own
   subdomain, which doesn't exist yet at this point anyway.
2. Stripe's `success_url` points at the new tenant's own subdomain
   (`School::frontendUrl()`), carrying a one-time token
   (`Password::broker()->createToken()`, reused rather than inventing a
   new mechanism).
3. `POST /api/v1/auth/signup/complete`, made from that subdomain,
   exchanges the token for a real session — this is what actually logs
   the admin in, on the correct origin. `SignupCompletePage` (frontend)
   is the page that makes this call; it is deliberately a public route,
   not behind `ProtectedRoute`, since there is no session yet when it
   loads.

This is deliberately session-based rather than token-based: no token to
leak from `localStorage`, no manual expiry/refresh logic. If a future
phase needs a public/mobile API, that would be a separate token-issuing
guard alongside these two, not a replacement for either.

## Frontend architecture

Feature-folder organization under `src/features/<module>/{pages,components,schemas}`,
plus shared layers:

```
src/
  api/          axios client, one endpoints file per module, crudFactory for
                simple CRUD resources, centralized query key registry
  app/          AppProviders (QueryClientProvider, ThemeProvider, AuthProvider,
                Toaster), queryClient config
  components/
    ui/         Reusable primitive kit (Button, Modal, DataTable, FormField,
                Tabs, ...), each with a Vitest test
    layout/     AppShell, Sidebar, Topbar, RoleBasedNav
    feedback/   ErrorBoundary, NotFound, Forbidden, LoadingScreen
  context/      AuthContext (tenant session + hasRole/hasPermission),
                PlatformAuthContext (separate `platform` guard session,
                Super Admin — see § Authentication), ThemeContext
                (runtime branding)
  features/     One folder per business module
  hooks/        useCrudResource, usePagination, usePermission, useDebounce, ...
  routes/       AppRouter (React.lazy per page), ProtectedRoute,
                PlatformProtectedRoute, PermissionRoute
  types/        Shared TypeScript types mirroring backend API Resources
  config/       env.ts (import.meta.env wrapper), constants.ts, navigation.ts
```

- **Route-based code splitting:** every page component in `AppRouter.tsx` is
  `React.lazy()`-loaded behind a single `<Suspense fallback={<LoadingScreen/>}>`,
  which is why the production bundle is ~245KB main + per-route chunks
  instead of one 759KB bundle.
- **Server state** is TanStack Query exclusively — no component holds
  fetched data in local `useState`. `queryKeys.ts` centralizes cache keys
  so invalidation after a mutation is consistent across features.
- **Forms** are react-hook-form + zod, with schemas colocated in each
  feature's `schemas/` folder — validation rules live in one place and are
  shared between the form's live validation and its submit-time check.
- **Permission-gated UI:** `usePermission().can('module.action')` drives
  both route access (`PermissionRoute`) and conditional rendering (hiding
  buttons/nav items a role can't use) — see [rbac.md](rbac.md) for how
  permission strings map to backend Policies.

### Runtime theming

Branding (primary/secondary color, logo, favicon) is **not** a build-time
Tailwind config — it's DB-driven. `GET /api/v1/settings/public` returns the
public settings (global defaults + school-specific overrides), and
`ThemeContext` applies them by setting CSS custom properties
(`document.documentElement.style.setProperty('--brand-primary', ...)`) on
boot. Tailwind's `@theme inline` (v4, CSS-first config) consumes those same
variables, so a School Admin changing "Primary Color" in Settings updates
the whole UI live, no rebuild or redeploy required. See
[configuration.md § Theming](configuration.md#theming--branding).

## Security posture

- **CSRF:** Sanctum's cookie/header double-submit pattern, automatic once
  `ensureCsrfCookie()` has run.
- **XSS:** React's default escaping; no `dangerouslySetInnerHTML` in the
  codebase.
- **SQL injection:** Eloquent/query builder only — no raw interpolated SQL
  anywhere in the codebase.
- **Authorization:** every mutating/listing endpoint is backed by a Policy;
  controllers call `$this->authorize(...)` or rely on `Route::apiResource`'s
  automatic policy resolution. `tests/Feature/Authorization/*` asserts
  cross-role and cross-school denial.
- **Rate limiting:** `throttle:10,1` on login, `throttle:5,1` on
  forgot/reset-password, `throttle:6,1` on verification-email resend, plus
  (Phase 13) a general `throttle:api` backstop — 150 requests/minute per
  authenticated user (or per IP if unauthenticated) — on every other
  endpoint, via a named `api` limiter registered in
  `AppServiceProvider::boot()`. Skipped in the `testing` environment: the
  `array` cache store persists for the whole `phpunit` process
  (`phpunit.xml`), so a real limit there would accumulate across all tests
  sharing an IP/user rather than resetting per test.
- **Security headers** (Phase 13): `App\Http\Middleware\SecurityHeaders`,
  appended globally, sets `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`,
  a restrictive `Permissions-Policy`, and `Strict-Transport-Security`
  (only when the request is already HTTPS — sending it over plain HTTP
  does nothing but confuse local dev). This app is a JSON API with no
  browser-rendered HTML of its own, so no CSP is set here — the SPA is a
  separate origin/build served by whatever sits in front of it (see
  [deployment.md § 4](deployment.md)), and CSP belongs at that layer.
- **Trusted proxies** (Phase 13): unset by default — `TRUSTED_PROXIES` env
  var (`bootstrap/app.php`) must be set explicitly once a reverse proxy
  sits in front, otherwise `X-Forwarded-*` headers are ignored and
  `Request::ip()`/`isSecure()` reflect the direct TCP connection, not the
  spoofable header a client could send.
- **Password policy** (Phase 13): production-only (gated on
  `app()->isProduction()` in `AppServiceProvider::boot()`, since
  `uncompromised()` makes a live Have I Been Pwned API call on every
  submission — undesirable in tests/local dev) — min 10 chars, mixed
  case, numbers, and breach-checked via `uncompromised()`. Local/testing
  keep Laravel's bare `min(8)` default.
- **File uploads:** routed through `spatie/laravel-medialibrary`, which
  enforces mime/size validation per collection (see `Student::DOCUMENT_COLLECTIONS`).
- **Audit trail:** `spatie/laravel-activitylog` on sensitive models (User,
  Role, Setting, Student, and enrollment actions), capturing actor,
  before/after, and timestamp — visible via the Audit Log admin page.
- **Password hashing:** bcrypt via Laravel's default hasher (`BCRYPT_ROUNDS=12`).
- **Dependency audit** (Phase 13): `composer audit` / `npm audit` both
  clean as of this pass — re-run before every production deploy, not just
  once.
- **Two-factor authentication** (Phase 15): mandatory TOTP for every
  account, no opt-out — see [mfa.md](mfa.md) for the full model (grace
  period, recovery codes, admin reset) and
  `App\Http\Middleware\EnsureMfaEnrolled` for enforcement.
- **Field-level encryption** (Phase 15): the most sensitive PII/financial
  columns (`Student.medical_info`, guardian national IDs, phone numbers,
  MFA secrets, etc.) carry Laravel's `encrypted`/`encrypted:array` cast —
  see [deployment.md](deployment.md)'s encryption-backfill deploy-order
  note for the one real gotcha (existing plaintext rows need a one-off
  backfill command run before the cast goes live, not after).
- **Data export & right to erasure** (Phase 15): self-service "export my
  data" / "delete my account" for any user, plus admin/platform-level
  bulk equivalents — see [api.md](api.md)'s "Data export" and "Account
  deletion & anonymization" sections. `AnonymizationService` documents
  exactly what's scrubbed vs. retained per model.
- **Configurable retention** (Phase 15): per-tenant `retention.*`
  Settings drive three scheduled commands (audit log pruning, expired
  data-export cleanup, opt-in stale-account anonymization) — see
  [deployment.md § 6](deployment.md#6-scheduled-jobs).

See [rbac.md](rbac.md) for the authorization model in depth and
[deployment.md](deployment.md) for production hardening (HTTPS, secure
cookie flags, `APP_DEBUG=false`, etc.).
