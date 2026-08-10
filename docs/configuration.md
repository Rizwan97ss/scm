# Configuration

There are three distinct layers of configuration in this system — knowing
which one a given value belongs in matters, because they have very
different change-and-deploy costs.

| Layer | Examples | Changing it requires |
|---|---|---|
| **Environment variables** | DB credentials, mail driver, app URL, CORS origins | Editing `.env`, restarting the process |
| **DB-driven settings** (`settings` table) | Branding colors, currency, timezone, date format, terminology labels, admission number format, notification toggles | A UI action (Settings page) or `PUT /api/v1/settings` — no deploy |
| **Code-level constants** (`config/*.php`, `src/config/constants.ts`) | Permission catalogue, pagination page size, debounce timing, upload size limits | A code change + deploy — these are things that would be *wrong* if an admin could change them per-school (e.g. the permission module list) |

The rule of thumb used throughout: if a School Admin should reasonably be
able to change it without asking a developer, it's a DB-driven setting, not
an env var or hardcoded constant.

## Environment variables

### Backend (`backend/.env`)

See `backend/.env.example` for the full annotated list. The
project-specific ones (beyond standard Laravel):

| Variable | Purpose |
|---|---|
| `FRONTEND_URL` | Canonical SPA URL, used to build links sent in emails (password reset, invites) |
| `FRONTEND_URLS` | Comma-separated origins allowed to call the API with credentials (CORS) |
| `SANCTUM_STATEFUL_DOMAINS` | Comma-separated hosts (no scheme) that get Sanctum's cookie-based SPA auth treatment |
| `DB_CONNECTION` | `sqlite` for local dev/tests (default), `mysql` for production — see [deployment.md](deployment.md) |

### Frontend (`frontend/.env`)

| Variable | Purpose |
|---|---|
| `VITE_API_URL` | Base URL of the Laravel API, no trailing slash. The SPA calls `{VITE_API_URL}/v1/...` and fetches the CSRF cookie from `{origin of VITE_API_URL}/sanctum/csrf-cookie`. |
| `VITE_APP_NAME` | Fallback app name shown before `/settings/public` has loaded on first paint |

Accessed only through `src/config/env.ts`, never `import.meta.env`
directly elsewhere — `env.ts` fails fast with a clear error if a required
var is missing, instead of `undefined` surfacing three layers deep in a
request.

## DB-driven settings

The `settings` table (`key`, `value`, `type`, `group`, `is_public`,
`school_id` nullable) is the mechanism for anything an admin should be able
to tune without a deploy. `school_id = null` rows are global defaults; a
row with the same `key` and a real `school_id` overrides the global default
for that school only. `App\Services\SettingsService` resolves the merge
(school override wins, falls back to global) and caches per-school.

Current settings groups (seeded by `SettingSeeder`):

| Group | Keys | Notes |
|---|---|---|
| `branding` | `primary_color`, `secondary_color`, `logo_url`, `favicon_url` | Public — drives runtime theming, see below |
| `localization` | `currency`, `currency_symbol`, `timezone`, `date_format`, `time_format` | Public |
| `academic` | `grade_level_label`, `section_label`, `term_label` | Public — this is how the system stays terminology-agnostic (a school can relabel "Grade" to "Year," "Section" to "Class," etc. without touching code) |
| `students` | `admission_number_format`, `admission_number_padding` | Not public (used server-side only, by `IdSequenceService`) |
| `notifications` | `email_enabled` | Not public |

`is_public` settings are exposed pre-login via `GET /api/v1/settings/public`
(so the login page itself can be branded); non-public settings only appear
in the authenticated `GET /api/v1/settings` response, gated by
`settings.view`.

**Adding a new setting:** add a row via `SettingsService::set()` in a
seeder (or a migration for a one-off backfill), give it a `group` (used
purely for organizing the Settings UI into sections), and decide
`is_public` based on whether it's needed before login. The Settings page
(`frontend/src/features/settings/pages/SettingsPage.tsx`) renders whatever
groups/keys come back from the API — it doesn't hardcode the list, so a
new setting appears in the UI automatically once seeded.

**Feature-flag-style toggles** (like `notifications.email_enabled`) follow
this same settings mechanism rather than a dedicated feature-flag system —
there wasn't a need for anything more elaborate (percentage rollouts,
targeting rules) at this stage. If a later phase needs that, it should be
a distinct concern layered on top of, not a replacement for, DB-driven
settings.

## Theming & branding

Tailwind v4's CSS-first config (`@theme inline` in `frontend/src/index.css`)
defines the design tokens (`--color-primary`, `--color-secondary`, spacing,
etc.) as CSS custom properties. `ThemeContext`
(`frontend/src/context/ThemeContext.tsx`) fetches `/api/v1/settings/public`
on app boot and overwrites the brand-specific ones —
`document.documentElement.style.setProperty('--brand-primary', value)` —
with whatever the resolved (school-override-or-global) `branding.*`
settings say. Because Tailwind's utility classes reference the CSS
variable rather than a compiled-in hex value, this takes effect
immediately, live, with no rebuild — a School Admin changing "Primary
Color" in Settings repaints the whole app on next data refresh.

This is also how multi-school white-labeling works going forward: two
schools on the same deployment can have different primary colors and logos
just by having different `settings` rows, no per-school build.

## Non-DB-driven constants

`config/permissions.php` (backend) and `src/config/constants.ts`,
`src/config/navigation.ts` (frontend) hold things that are deliberately
*not* settings — the permission module/action catalogue, sidebar nav
structure, pagination size, debounce timing, upload size ceiling. These
are consistent-by-design across all schools on a deployment; making them
DB-editable would mean a School Admin could, for instance, remove the
`students.delete` permission from existence rather than just not grant it
to a role — a category error. See [rbac.md](rbac.md) for how to extend the
permission catalogue correctly (add to `config/permissions.php`, not to a
runtime setting).
