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

Every school-owned table has a `school_id` column. Two pieces enforce
isolation:

- **`App\Traits\BelongsToSchool`** — applied to tenant-scoped models. Auto-fills
  `school_id` from the acting user on create, and registers...
- **`App\Scopes\SchoolScope`** — a global Eloquent scope that adds
  `WHERE school_id = ?` to every query against that model, scoped to the
  authenticated user's `school_id`. Super Admin (no `school_id`) bypasses it.

**`User` is the one deliberate exception.** Applying `BelongsToSchool` to
`User` causes infinite recursion (the scope needs to resolve the acting
user, which is itself a `User` query). Instead, `User` has a manual
`scopeInSchool()` that controllers/services call explicitly wherever they
list or resolve users. If you add a new model that needs to query `User`
from inside a global scope or policy, be aware of this — don't reach for
`BelongsToSchool` on `User` again.

New tenant-scoped tables should: add `school_id` (nullable only if the row
can be legitimately global, like a Setting default), use `BelongsToSchool`,
and get a policy that checks `belongsToActorsSchool()` (see
[rbac.md](rbac.md)).

## RBAC

`spatie/laravel-permission` with the **teams** feature enabled
(`team_foreign_key = 'school_id'` in `config/permission.php`). This means
role/permission assignments are scoped per school natively — a user can hold
"Teacher" in School A and "Accountant" in School B without the package
needing any custom code.

- **Team context per request:** `App\Http\Middleware\EnsureSchoolContext`
  calls `PermissionRegistrar::setPermissionsTeamId($user->school_id ?? 0)`
  before every authenticated request, so `$user->can(...)` checks resolve
  against the right school automatically.
- **Super Admin** uses the reserved team id `0` — a sentinel, not a real
  school row (team columns have no FK constraint, so this is safe) — and
  gets a `Gate::before` bypass for all abilities.
- **Full detail:** see [rbac.md](rbac.md) for the permission catalogue,
  default role matrix, and how to add a new role or module.

## Authentication

Laravel Sanctum in **SPA mode** (cookie session, not bearer tokens):

1. Frontend calls `GET /sanctum/csrf-cookie` before the first mutating
   request (`ensureCsrfCookie()` in `src/api/client.ts`), which sets the
   `XSRF-TOKEN` cookie.
2. `POST /api/v1/auth/login` accepts email *or* username + password, verifies
   via `Hash::check()`, and calls `Auth::login()` — this establishes a
   server-side session, no token is issued or stored client-side.
3. Every subsequent request rides on the session cookie; axios sends
   `withCredentials: true` and Laravel's `statefulApi()` middleware
   recognizes the frontend's origin as stateful (see
   `SANCTUM_STATEFUL_DOMAINS` in [configuration.md](configuration.md)).
4. `AuthContext` (frontend) caches the `UserResource` shape returned by
   login and re-fetches via `GET /api/v1/auth/me` on app boot to restore
   session state after a refresh.

This is deliberately session-based rather than token-based: no token to
leak from `localStorage`, no manual expiry/refresh logic, and it matches
how a same-origin-in-production SPA should authenticate. If a future phase
needs a public/mobile API, that would be a separate token-issuing guard
alongside this one, not a replacement for it.

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
  context/      AuthContext (session + hasRole/hasPermission), ThemeContext
                (runtime branding)
  features/     One folder per business module
  hooks/        useCrudResource, usePagination, usePermission, useDebounce, ...
  routes/       AppRouter (React.lazy per page), ProtectedRoute, PermissionRoute
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

See [rbac.md](rbac.md) for the authorization model in depth and
[deployment.md](deployment.md) for production hardening (HTTPS, secure
cookie flags, `APP_DEBUG=false`, etc.).
