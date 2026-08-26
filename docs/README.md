# School Management System — Documentation

A multi-school-ready School Management System: Laravel 13 (PHP 8.4) REST API +
React 19/TypeScript SPA, MySQL in production (SQLite for local dev/tests).

This `docs/` set covers what's been built so far — **Phase 0 through Phase 5**
of the build roadmap (foundations, identity & access, academic structure,
student management + parent portal basics, attendance, and examinations
including a full online examination system). Later phases (billing, HR,
library/transport/hostel, communications, reporting) will extend these same
docs as they land; see [roadmap.md](roadmap.md) for what's next.

## Start here

| Doc | Read this for |
|---|---|
| [architecture.md](architecture.md) | System design: multi-tenancy, RBAC, layering, how the pieces fit together |
| [tenant-guide.md](tenant-guide.md) | For a School Admin, not a developer — the complete setup flow for a new school, in dependency order |
| [setup.md](setup.md) | Getting a local dev environment running from a clean checkout |
| [database.md](database.md) | Schema, ERD, and the reasoning behind key modeling decisions |
| [rbac.md](rbac.md) | The permission model, default roles, and how to add a new role or permission |
| [api.md](api.md) | Every endpoint currently exposed, grouped by module |
| [configuration.md](configuration.md) | Environment variables, DB-driven settings, theming/branding, feature flags |
| [testing.md](testing.md) | How to run and extend the backend and frontend test suites |
| [deployment.md](deployment.md) | Production build & deploy: MySQL cutover, queues, storage, mail, backups |
| [roadmap.md](roadmap.md) | What's done, what's next, and the conventions new modules must follow |

## Quick facts

- **Repo layout:** `backend/` (Laravel API), `frontend/` (React SPA), `docs/` (this folder) — sibling folders, no shared package manager.
- **Auth:** Laravel Sanctum, cookie-based SPA session (not token-based) — see [architecture.md § Authentication](architecture.md#authentication).
- **Multi-tenancy:** every school-owned row carries `school_id`; a global Eloquent scope enforces isolation automatically — see [architecture.md § Multi-tenancy](architecture.md#multi-tenancy).
- **RBAC:** `spatie/laravel-permission` with *teams* keyed on `school_id`, 12 default school-scoped roles + a cross-school Super Admin — see [rbac.md](rbac.md).
- **Demo login:** `admin@riverside-demo.test` / `password` (School Admin, seeded "Riverside Demo School"); `superadmin@example.com` / `password` (Super Admin, all schools).
- **Current test status:** 108/108 backend PHPUnit tests passing, frontend Vitest suite passing, `tsc --noEmit` and `eslint` clean, production frontend build verified (code-split).
