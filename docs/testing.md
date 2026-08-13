# Testing

## Backend (PHPUnit)

```bash
cd backend
php artisan test              # full suite
php artisan test --filter=StudentAdmissionTest
```

Tests run against SQLite `:memory:` (configured in `phpunit.xml`), with
`RefreshDatabase` on every test class — no shared state, no need for a
separate test database.

**108/108 tests passing** across:

- `tests/Feature/Auth` — login (email or username), logout, password
  change, forgot/reset password, throttling
- `tests/Feature/Users` — CRUD, role assignment, status changes, cross-school denial
- `tests/Feature/Roles` — CRUD, permission matrix assignment
- `tests/Feature/Settings` — get/update, public vs. authenticated visibility, global-vs-school override resolution
- `tests/Feature/Academic` — `AcademicYearTest` (CRUD, date validation, activation), `SectionTest` (CRUD, the Class-Teacher-owns-their-section update rule — added as a regression test after that exact rule's policy signature broke every Section request, see below), `TimetableConflictTest` (double-booking rejection). **Known gap:** Department, GradeLevel, Subject, Room, Holiday, and TimetablePeriod have no dedicated test files yet — they're exercised indirectly wherever another test's setup creates one via factory, but not through their own HTTP endpoints. Follow `SectionTest.php`'s shape if you add one.
- `tests/Feature/Students` — admission (incl. inline guardian creation), sequential admission-number formatting, promotion/transfer/withdrawal/graduation/reactivation, enrollment history, document upload, import/export, row-level section scoping for Teachers
- `tests/Feature/Attendance` — student attendance (bulk marking, upsert-not-duplicate, section-ownership enforcement for Teachers, corrections, summaries, percentage weighting, cross-school isolation, exact-date filtering), staff attendance (bulk marking, self check-in/check-out, self-visibility without the blanket permission, corrections, exact-date filtering)
- `tests/Feature/Exams` — `GradingScaleTest` (CRUD, band replacement), `ExamConfigurationLookupsTest` (Phase 16, `exam-types`/`assessment-component-types` CRUD + permission + unique-code), `ExamTest` (create-with-nested-groups-and-components, upsert-not-replace on group/component edits, teacher-can/cannot-enter-marks, marks-cannot-exceed-max, report-card grade computation, per-subject-group masking for Student/Parent, cross-school isolation), `ExamSubjectGroupResultTest` (Phase 16, multi-component subject totals incl. the worked Online-MCQ+Written+Oral+Practical example, component-level partial-absence rule, group publish/unpublish authorization — Admin always, Class Teacher only for their own section, plain Teacher never — and the resulting student-visibility flip independent of the whole exam's own publish state, component delete cascading its marks), `OnlineExamTest` (question CRUD with exactly-one-correct-option validation, teacher-can/cannot-configure, student auto-graded attempt end-to-end, section/max-attempts/resubmit eligibility rules, attempt-privacy denial, Phase 16 negative-marking scoring incl. the floor-at-zero rule and blank answers never being penalized), `QuestionImportTest` (Phase 16, Excel MCQ import happy path with a partial-failure report, within-file duplicate-question detection, invalid `correct_option` detection, `questions.import` permission on both the import and template-download endpoints), `AutoSubmitExpiredOnlineTestsCommandTest` (Phase 16, sweeps an expired `in_progress` attempt to `submitted` with a real score, degrades without crashing for a tenant with no School Admin to attribute the sweep to), `TermResultTest` (weighted-average computation, section-rank, parent visibility)
- `tests/Feature/Authorization` — cross-role and cross-school denial matrix
- `tests/Feature/Dashboard` — role-context switching (staff/teacher/parent), scoped counts, today's-attendance-marked count
- `tests/Unit/Services` — `StudentIdGeneratorService`, `StudentEnrollmentService`, `SettingsService` in isolation, no HTTP
- `tests/Feature/Security` (Phase 15) — `MfaTest` (setup/confirm/login-challenge round trip on both guards, recovery-code consumption, `EnsureMfaEnrolled`'s grace-period/exemption matrix, admin reset), `DataExportTest`, `AnonymizationTest` (individual + whole-school offboarding, cross-tenant isolation), `RetentionTest`, `PermissionRolloutTest`, plus the pre-existing `SecurityHeadersTest`

**Phase 15 note on queued jobs**: `QUEUE_CONNECTION=sync` in `phpunit.xml`
— `GenerateDataExportJob` (the first real queued job in this app) runs
inline within the same request/test, so `DataExport` rows are already
`ready` by the time a dispatching request returns; no `Queue::fake()`
needed to observe the effect, only to assert *that a dispatch happened*
without caring about the result.

**Gotcha hit writing these**: don't run two `php artisan test` invocations
concurrently (e.g. one in the foreground, one backgrounded) — tenant
SQLite databases are named after the landlord `School` row's
auto-increment id (`tenant1`, `tenant2`, ...), which resets to 1 every
time `RefreshDatabase` recreates the landlord `:memory:` DB. Two test
processes racing to create "tenant1" at the same time produces file-lock
and disk-I/O errors that look like real bugs but are just self-inflicted
concurrency — run backend test suites one at a time.

### Test helpers

`tests/TestCase.php` is the base class: sets a `Referer` header (Sanctum's
stateful-session check requires it) and clears Spatie's permission cache in
`setUp()` (the cache persists across tests in the same process otherwise,
causing permission checks to pass/fail based on test order rather than
actual state).

`tests/Concerns/InteractsWithSchool.php` provides:
```php
$school = $this->createSchool();
$user = $this->createUserWithRole($school, 'Teacher');
$superAdmin = $this->createSuperAdmin();
$this->actingAsInSchool($user)->getJson(...);   // sets the Sanctum session + permission team context together
```
Use these rather than `Sanctum::actingAs()` directly — plain
`actingAs()` doesn't set the Spatie permission team context, so
permission checks would silently evaluate against the wrong (or no)
school.

### Writing a new test

Follow the existing `tests/Feature/<Module>/*Test.php` pattern: one test
class per entity/concern, `InteractsWithSchool` + `RefreshDatabase`
traits, and at minimum cover (1) the happy path, (2) a permission-denied
case, and (3) a cross-school isolation case if the entity is tenant-scoped.
`StudentAdmissionTest::test_teacher_can_only_see_students_in_their_assigned_sections`
is a good template for row-level-scoping tests specifically.

## Frontend (Vitest + Testing Library + MSW)

```bash
cd frontend
npm run test          # watch mode
npm run test -- --run # single run (CI)
npm run typecheck     # tsc --noEmit
npm run lint          # eslint
npm run build          # production build, also type-checks
```

MSW (`src/testing/mswServer.ts`, `mswHandlers.ts`) intercepts network
calls at the fetch/XHR layer so component tests exercise real
axios/TanStack Query code paths without hitting a real backend.
`renderWithProviders` (`src/testing/testUtils.tsx`) wraps a component with
the same providers (`QueryClientProvider`, `AuthProvider`, router) it'd
have in the real app.

Covered so far: UI kit primitives (`Button`, `Modal`), `LoginForm`
(validation errors, server-error display, successful submit), the student
admission zod schema, and `usePermission`.

**jsdom gotcha already hit:** Radix UI components call `ResizeObserver`,
which doesn't exist in jsdom — polyfilled once in `src/testing/setupTests.ts`.
If a new component test fails with `ResizeObserver is not defined`, that
polyfill file is where to look, not the component.

**Accessible-name gotcha already hit:** `getByLabelText` matches the
computed accessible name, not raw `textContent` — a required-field
asterisk rendered as visible text was leaking into the accessible name and
breaking label-text queries. Fixed at the source (`FormField`'s asterisk
has `aria-hidden="true"`, which is also the correct a11y fix, not just a
test workaround) rather than papering over it in test queries.

## Manual/browser smoke testing

Type-checking and automated test suites verify code *correctness* — they
don't verify feature *correctness* end-to-end the way a human clicking
through the app does. This project's most significant bug (see
[rbac.md § Frontend permission checks](rbac.md#frontend-permission-checks))
was invisible to every automated test because none of them exercised the
full login → nav-render integration path against real backend response
shapes; it was only caught by driving the actual running app in a browser.

**Practice going forward:** before marking any UI-facing change complete,
start both dev servers and drive the actual feature in a browser — headless
via `chromium-cli`/Playwright is fine and is what was used here (there's no
project-specific driver script committed, since the dev-server + login flow
is simple enough to script inline each time: `npm run dev` in `frontend/`,
`php artisan serve` in `backend/`, log in as the seeded School Admin, then
`nav`/`wait-for`/`click`/`screenshot` the flow being changed). Check
`console --errors` after each step — a page can render its shell
successfully while every data fetch underneath silently 500s.

**It happened again in Phase 4, twice over.** Manually driving the new
Attendance pages surfaced two more bugs zero PHPUnit tests caught: a
pre-existing `SectionPolicy` signature bug that fatal-errored *any* request
touching Sections (found because the Take Attendance page's section
dropdown 500'd — masked by the browser as a CORS error, since a fatal
error thrown that early in the stack skips the middleware that adds CORS
headers), and a systemic `date`-column exact-match bug that hit four
separate call sites (a "Check In" button whose card kept saying "not
checked in" right after succeeding was the tell). Both are now covered by
regression tests, but neither would exist without first seeing the feature
visibly wrong in a real browser — a passing test suite does not mean a
feature works; it means the tests that exist pass.

**And again in Phase 5, three times over — none of which a passing 108/108
suite caught.** Manually driving the exam-creation flow (grading scale →
question bank → exam → subjects → marks → online-test config → publish →
report card, as both a School Admin and, separately, as a real student
taking the online test and a parent viewing the result) surfaced: (1)
`useCrudResource`'s cache-invalidation call built its query key with no
arguments while the live list query was keyed with pagination params, so
`invalidateQueries` never matched anything — every one of the 13 pages
built on this hook kept showing stale (often empty) lists right after a
successful create, with a toast confirming success sitting right above
the unchanged table. Caught by literally reading "No questions yet" in a
screenshot taken one second after two questions were created. (2)
`ExamController::show()` crashed with a raw 500 (`TypeError`, not a clean
404) when given a non-numeric ID — surfaced only because a test-script bug
briefly sent the app to `/exams/undefined`, which is exactly the kind of
malformed URL a real bookmark or browser back-button glitch can produce.
(3) The parent-facing exam tab had no consolidated-term-result view at
all, even though the backend endpoint for it had existed since this same
phase — an omission no backend test could ever catch, since the endpoint
itself worked fine; nothing was calling it. All three are fixed; see
[roadmap.md § Done (Phase 5)](roadmap.md#status) for the specifics. The
pattern holds: **automated tests confirm the code you wrote does what you
told it to; only driving the UI confirms you told it to do the right
thing.**

**Phase 6 (Sub-phase B, Stripe integration) needed a variant of this
same lesson for a backend-only feature with no UI at all: hand-built
webhook payloads aren't a substitute for a live provider.** 9 tests
posting synthetic Stripe-shaped JSON directly at `/stripe/webhook` all
passed — and still missed a real bug, because a hand-built single-event
payload can't reproduce what happens when Stripe fires *two* real events
for the same underlying change in quick succession
(`customer.subscription.created` and `invoice.payment_succeeded` both
fire for a new trial subscription). Only a real Stripe test-mode
account, a real `stripe listen --forward-to` session, and a real
trial subscription surfaced the race: `billing_status` ended up
`'active'` when the actual subscription — confirmed by both Stripe's
dashboard-equivalent API response and Cashier's own local
`subscriptions.stripe_status` — was `'trialing'`. See
[roadmap.md § Status](roadmap.md#status) for the fix. **For any
integration with an external provider (payments, email, SMS — anything
that fires webhooks or has its own async state machine), hand-built
test payloads verify your parsing logic; they cannot verify your
handling of that provider's actual event ordering and timing. Budget
for a real test-mode account and a real end-to-end run before calling
the integration done**, the same way a UI feature needs a real browser.

**Phase 6 (Sub-phase E, seat limits) surfaced a lesson about the smoke
scripts themselves, not the app.** A multi-account script (healthy
school, then past-due school, then at-limit school, one shared
Playwright `page` reused across logins) failed consistently at the
third account's login step, but the exact same login-and-navigate
sequence passed cleanly every time when run in isolation. The cause:
the script's `logout()` helper was called between accounts wrapped in
`.catch(() => {})` (to tolerate a logout button occasionally not being
where expected), which meant a *silently failed* logout left the
browser still authenticated as the previous account; the next
`login()` call's own success check (waiting for "Welcome back," present
on every account's dashboard) then passed without the login form ever
actually being used, so the rest of the script ran against the wrong
tenant entirely. Fixed by having `login()` call
`page.context().clearCookies()` before every attempt, guaranteeing a
clean unauthenticated state regardless of whether a prior UI-driven
logout succeeded, and deleting the now-redundant `logout()` calls.
**A second, separate issue then surfaced once login was fixed: the
plan-limit-blocked student-admission step timed out waiting for a
dropdown option that never appeared, because the Tinker-provisioned
"at its limit" test school had zero Academic Years —
`SchoolProvisioningService::provision()` deliberately doesn't create
one, and the admission form's academic-year field is genuinely empty
for a school with none.** Not an app bug, a test-fixture gap, fixed
by creating one Academic Year for the test school before running the
script. Neither issue would have been caught by re-running the same
flawed script twice; both needed isolating the failing step
(`diag-402.js`, login-and-navigate only, nothing else) and comparing
its clean result against the full script's consistent failure at the
same point — the same isolate-and-compare approach used for the
Phase 5 `useCrudResource` bug above. **The general lesson: a
defensively `.catch()`-wrapped cleanup step that silently no-ops is
worse than one that throws — it doesn't fail loud, it fails by
corrupting every assertion downstream of it.** Prefer state that's
reset by construction (clear cookies, fresh browser context) over
state that's reset by a UI action that might not land.

**Phase 7 (Teacher module: homework/assignments, remarks) needed a
re-seed step the earlier phases hadn't: adding a new permission module
to `config/permissions.php` and `SchoolProvisioningService`'s role
matrix does nothing for a school that was already provisioned before
the code change.** `PermissionSeeder`/`RolePermissionSeeder` only run
at provisioning time (or when explicitly re-invoked) — the demo school
seeded earlier in this session had no `homework.*`/`remarks.*`
permissions on any role until `php artisan db:seed
--class=PermissionSeeder` and `--class=RolePermissionSeeder` were
re-run by hand. First symptom: the "Homework" nav item never rendered
for a Teacher login, a 30-second `page.click('text=Homework')` timeout
that looked like a routing bug but was a permissions-catalogue gap. Both
seeders are idempotent (`firstOrCreate`/`syncPermissions`), so re-running
them against a school that already has the older matrix is always safe
— do this immediately after adding any new module to the permission
matrix, not just on a fresh `migrate:fresh --seed`.

**The same phase's smoke script then surfaced three more
Playwright-specific traps, none of them application bugs, all worth
the same discipline as the two above:**

1. **`text=` matches a live, un-submitted `<textarea>`'s current
   value, not just persisted content.** A step that filled a remark's
   body and then waited for `text=<the same body text>` as its success
   signal passed even when the create request silently never fired —
   the wait was satisfied by the textarea still sitting there with
   what had just been typed into it, not by a re-fetched, persisted
   remark. The fix: wait for something the *server* confirms — the
   success toast, then the modal's field actually leaving the DOM
   (`{ state: 'detached' }`) — before trusting that the same text now
   appearing on screen reflects a saved record.
2. **Unscoped `text=`/`:has-text()` selectors are case-insensitive
   substring matches across the *whole page*, including elements
   hidden behind an open modal's overlay.** `button:has-text("Add
   remark")` matched both the modal's own submit button *and* the
   outer "Add Remark" trigger button sitting behind it (case differs,
   the match doesn't care) — two matches sends Playwright into a
   strict-mode retry loop that exhausts the full timeout rather than
   failing fast. Scoping the selector to `[role="dialog"]` (or keying
   off a real DOM attribute like `button[type="submit"]`) resolved it
   immediately. The general rule: any selector meant for "the button
   inside the modal I just opened" needs `[role="dialog"]` in front of
   it, full stop — the page almost always has a same- or
   similar-labelled trigger sitting right behind it.
3. **Repeated runs without cleanup make broad, page-wide text checks
   increasingly unreliable, not just slow.** Each successful run left
   a new homework row behind; once several had piled up, a
   page-wide `text=Submitted` wait started matching a *different*
   row's "Not submitted" badge (again, case-insensitive substring —
   "submitted" is inside "Not submitted") before the row actually
   under test had updated. Scoping the check to the one row
   (`page.locator('tr', { hasText: TITLE })`) fixed the ambiguity; a
   generated-unique title per run (`'Algebra Worksheet ' +
   Date.now()`) prevented the underlying pile-up from recurring.
   Smoke-test fixtures need the same "clean up after yourself, don't
   rely on the next run to not care" discipline as automated tests —
   an ad hoc script is easy to treat as disposable and forget to reset.

None of these three were caught by adding more `waitForTimeout()`
padding — that's the wrong instinct here, since the underlying
mechanism (a mutation that either fires or doesn't, a selector that
either matches one element or several) doesn't get more correct with
more time, only more likely to coincidentally pass. Each was only
resolved by asking what specifically proves success — a persisted
record, a uniquely-scoped element — and asserting on that instead of a
plausible-looking page-wide text match.

**Phase 8 (Fees/Billing/Accounting) needed the Phase 7 re-seed lesson
again — this time for a role, not a module.** Adding `fees.*`/`invoices.*`
to `config/permissions.php` and `SchoolProvisioningService`'s matrix, then
re-running `PermissionSeeder`/`RolePermissionSeeder`, wasn't quite enough
on its own: the Accountant role was granted `fees.*`/`invoices.*` but not
`academic-structure.view`, which the Fee Structures page's "Generate
Invoices" modal needs to populate its grade-level/section dropdowns. The
dropdowns silently rendered empty (403s on `GET /grade-levels`/`GET
/sections` logged to the console, easy to miss) rather than failing
loudly, so the first visible symptom was a `[role="option"]` selector
timing out on "Grade 1" — indistinguishable at a glance from a real
frontend bug. The general lesson from both phases together: a new
permission module needs auditing against *every* page it powers, not just
the page whose name matches the module — a Fee Structures page reads
academic-structure data too, and the role granted the new module's
permissions needs whatever supporting-data permissions those pages
already assume.

**The same phase's smoke script then surfaced a real, deterministic
backend bug that no PHPUnit test caught, because PHPUnit never runs
through `php artisan serve`.** Bulk-generating invoices for a section (a
loop creating one `Invoice` + `InvoiceItem` per student, all originally
wrapped in one outer `DB::transaction()`) failed with a 500 every single
time through the browser — `SQLSTATE[HY000]: General error: 14 unable to
open database file` — while the *identical* service call succeeded fully
via `php artisan tinker`. Diagnosis (see
[database.md § Key modeling decisions](database.md#key-modeling-decisions)
for the full writeup) ruled out, in order: a missing permission (fixed
separately, see above, but didn't resolve this), duplicate stray
`php artisan serve` processes left over from an earlier failed start
(found via `Get-Process php`, killed, restarted clean — didn't resolve
it either), and SQLite lock contention (`busy_timeout`/WAL journal mode —
also didn't resolve it). Instrumenting the service with `Log::info()`
calls at each step pinpointed the actual pattern precisely: the *first*
write in the transaction always succeeded, the *second* always failed,
under HTTP specifically, every time, regardless of which student was
processed first. The fix was structural, not configuration: one
transaction per student instead of one transaction around the whole
batch (see `InvoiceService::generateFromStructure()`). This is arguably a
better design regardless (a batch of 30 students shouldn't lose 29
correctly-generated invoices because student 30 had bad data), but it was
found because the browser flow failed and Tinker didn't — a reminder that
"the service method works" (provable via Tinker or a unit test) and "the
endpoint works" (only provable by actually going through HTTP, ideally a
real browser) are different claims, and bulk/multi-write operations are
exactly where they can diverge.

**The same run also surfaced two small but real frontend bugs:** (1) the
"Generate Invoices" modal's section dropdown listed every section
school-wide by bare name (`"A"`, `"B"`) with no grade qualifier — since
multiple grades legitimately have a section named "A", this was
genuinely ambiguous, not just a test-selector inconvenience; fixed by
filtering to the fee structure's own grade level when it has one, and
qualifying the label with the grade name otherwise. (2) `InvoiceDetailPage`'s
void confirmation used `ConfirmDialog` without setting `confirmLabel`,
so the button read the generic default "Confirm" instead of something
that actually named the destructive action — fixed to `"Void invoice"`,
which is also just better UX for a real user, not only a test-friendlier
selector.

**Phase 9 (Staff/HR: designations, leave, payroll) surfaced a genuine
permission-design bug the smoke run caught immediately, and a subtle
false-positive in the test script that took real digging to pin down.**

A Teacher applying for their own leave 403'd loading the leave-type
dropdown, even though filing a leave request is deliberately unpermissioned
for any staff member. The bug: `LeaveTypePolicy::viewAny()` required
`leave.view`/`leave.manage`, which a Teacher legitimately never holds —
config (the leave-type catalog) was accidentally gated *more* strictly
than the request that depends on reading it. Fixed to `$user->isStaff()`,
matching the self-service rule already established for the request itself.
The general lesson, worth stating plainly: when an action is deliberately
self-service/unpermissioned, audit every *read* that action's own UI
depends on — a permission gate one level removed from the actual write is
just as effective at blocking a legitimate user, and easy to miss because
the write path itself looks correctly configured in isolation.

**The salary-structure creation step then passed on one run and produced
a false "OK" on another, with the row never actually existing in the
database — traced to the exact `text=` false-positive trap already
documented above, just with a Select's displayed value instead of a
textarea's.** After submitting the "New Salary Structure" form, the
script waited for `text=Thomas Teacher` to prove success — but if the
`POST` silently failed (422, or any rejected mutation) and the modal
stayed open, the still-open form's own `#user_id` Select was *also*
showing "Thomas Teacher" as its selected value, satisfying the same
page-wide text match with nothing actually persisted. `php artisan
tinker` confirmed zero `salary_structures` rows existed for the user
despite the script reporting success. Root cause investigation ruled out
several plausible-looking culprits before landing on the real one:
stray duplicate `php artisan serve` processes from an earlier failed
start (found via `Get-Process php`, genuinely worth checking but not
this bug), and a lingering toast overlapping the next page's top-right
action button (`<Toaster position="top-right">` is mounted once at the
app root in `AppProviders.tsx` and doesn't unmount on route changes, so
a still-visible "User updated" toast really can intercept a click in
that corner — worth knowing generally, but not this bug either). The
actual fix had two parts: (1) rewrite the test to wait for the *modal to
close* (`#user_id` reaching `{ state: 'detached' }`) before trusting any
subsequent text match, exactly the discipline the Phase 7 lessons above
already prescribe; (2) a real, if latent, application bug the digging
surfaced — `useCrudResource`'s `createMutation`/`updateMutation`/`removeMutation`
had no `onError` handler at all, so *any* page using the hook without its
own try/catch (which turned out to be most of them — Departments, Fee
Categories, Designations, Leave Types, Salary Structures, ...) would fail
a mutation completely silently: no toast, and the modal stays open with
no indication anything went wrong, because the unhandled rejection skips
past the `setModalOpen(false)` line the caller never wrapped in a
try/catch. Fixed once, centrally, by adding a shared `onError` to all
three mutations in `useCrudResource.ts` rather than patching each page —
every page using the hook now shows a real error toast on failure, not
just the two-or-three pages (`GradingScalesPage` among them) that
happened to add their own try/catch already.

**Re-running the same smoke script a second time within the same
calendar month produces one expected, non-bug failure: "mark payslip
paid" has nothing left to click, because the previous run's payslip for
that staff member is already `paid`.** Payslips are unique per
`(user_id, month, year)`, so a second `generate` call correctly skips
them — this is idempotency working as designed, not a regression. Smoke
scripts that touch monthly/period-scoped data should either accept this
(and confirm via a quick `tinker` check when a run looks suspicious,
rather than assuming a new bug) or generate for a period unlikely to
already exist, the same "make your own fixture unique" discipline Phase
7's lessons already established for row names.

**Phase 10 (Library, Transport, Hostel, Front Desk) surfaced one real
permission-design bug, caught by the smoke run rather than by any of the
19 new automated tests — a role-matrix gap, not a code defect.** The
script drove all four modules end to end: a Librarian creates a book,
issues it to a student, and confirms the available-copy count drops and
recovers on return; a Transport Staff member creates a vehicle and a
two-stop route, then assigns a student to both; an HR Staff member
creates a hostel and a room, allocates and then vacates a student; a
Receptionist logs and checks out a visitor. On the first run, three of
the four "pick a student" steps failed identically: the Select dropdown
for choosing a student stayed empty and the subsequent click on a
never-rendered option timed out. The browser console showed why —
`GET /students` was returning 403 for the Librarian, Transport Staff, and
HR Staff sessions. None of those three roles had ever been granted
`students.view`: Librarian and Transport Staff started this phase with
only their own module's `view`/`manage` plus `dashboard.view`, and HR
Staff — despite already holding `hostel.manage` — had never needed to
look up a student for anything in Phase 9. Each role legitimately needs
to find a student to do the job its own module exists for (issue them a
book, assign them transport, allocate them a bed), the same reasoning
that already justified `students.view` on the Receptionist role from an
earlier phase. Fixed by adding `students.view` to all three roles in
`SchoolProvisioningService`'s permission matrix and re-running
`php artisan db:seed --class=RolePermissionSeeder --force` against the
already-provisioned demo school — a schema/code change alone wouldn't
have taken effect for an existing tenant without that re-sync step. All
15/15 smoke assertions passed on the second run. The general lesson again:
a permission gap in a *supporting* lookup (here, a Select's data source)
fails exactly like a gap in the primary action, and unit/feature tests
that create their own fully-permissioned test users straight from a
factory won't catch it — only a smoke test logging in as the actual
seeded role surfaces it.

**Phase 11 (Certificates & ID Cards, Notice Board, Communication)
surfaced two real application bugs before the browser run, and a real
pre-existing bug — not introduced by this phase — the browser run caught
that no prior phase's smoke test was positioned to find.**

The first two were caught by direct inspection while building, the same
way Phase 10's `HostelResource` bug was: the certificate PDF's Blade view
had a `<table class="meta">` whose own CSS class declared `display: flex`
(leftover from an earlier flex-based layout draft), and dompdf's table
layout engine crashed outright — `Dompdf\Exception: Parent table not
found for table cell` — the moment a real `<td>` rendered inside a
`<table>` that had lost its table formatting context. Fixed by giving the
table a non-conflicting class name. `NoticeController::store()` returned
the freshly created row via `->load(self::WITH)`, but `is_published`
isn't part of the create payload (a notice always starts unpublished, by
design) — `load()` only eager-loads relations, it never re-reads scalar
columns from the database, so the in-memory model's `is_published` stayed
PHP `null` instead of picking up the migration's `default(false)`, and
`test_school_admin_can_create_and_publish_a_notice` caught the create
response asserting `is_published: false` but actually receiving `null`.
Fixed by switching to `->fresh(self::WITH)`, which genuinely re-fetches
the row. Both were caught by backend tests or manual review, before a
browser was ever involved — worth noting because it means the "manual
smoke testing catches what automated tests can't" lesson this doc keeps
repeating isn't the whole story; ordinary test assertions and just reading
the diff still catch plenty on their own.

The browser run itself (12/12 after fixes) drove all three sub-features
in one script: School Admin creates a certificate template and issues it
to a student, confirms the issued certificate is listed and its PDF link
resolves; a student's and a staff member's ID card PDF links are checked
the same way; School Admin creates a notice, publishes it, and creates a
second event-type notice with a date; a Teacher confirms the published
notice is visible with no manage actions and no draft ever shown; School
Admin sends an announcement to students; the target student confirms an
unread badge and the announcement text appear in the new notification
bell, and that clicking it clears the badge. Verifying the PDF links
needed a different technique than every prior phase's smoke script used:
`page.request.get(url)` (Playwright's request client, not a real page
navigation) doesn't automatically satisfy Sanctum's
`EnsureFrontendRequestsAreStateful` middleware, which decides whether to
treat a request as session-authenticated by checking its `Referer`/
`Origin` header against `SANCTUM_STATEFUL_DOMAINS` — a plain
`page.request.get()` call sends neither, so the request silently falls
through to token-auth (no token present) and 401s even though the
browser's own cookies are otherwise being sent correctly. Adding
`headers: { Accept: 'application/json', Referer: BASE + '/' }` to the
`page.request.get()` calls made them resolve as the actual authenticated
session, returning a real `200`/`application/pdf` instead of a 401 —
worth keeping as the standard pattern for any future smoke test that
needs to verify a download link's response, not just its `href`.

The third bug is the significant one: **every PDF/receipt/report-card
download link in the entire app — going back to Phase 8 — was broken for
a real user, and no prior phase's smoke test could have caught it.**
`feeCategoriesApi`-style `*PdfUrl()` helpers (`fees.ts`'s
`receiptPdfUrl`, `hr.ts`'s `receiptPdfUrl`, `exams.ts`'s
`reportCardPdfUrl`, `dashboard.ts`'s `childReportCardPdfUrl`) all
returned a bare relative path like `/payslips/1/receipt/pdf`, rendered
directly as `<a href={...} target="_blank">`. With no dev-server proxy
bridging the SPA's own origin (`localhost:5173`) and the API's
(`localhost:8000`), a real click resolves that href against the SPA's
*own* origin — hitting React Router's catch-all `NotFound` page, not the
backend, every single time. Every prior phase's smoke test happened to
check these links the same way this phase's script initially did before
the fix above — `page.request.get()` with the relative path manually
reconstructed against the API's own origin — which incidentally worked
around the exact bug a real click would hit, so it was never actually
exercised as a user would exercise it. Fixed with one new shared helper,
`apiFileUrl()` (builds `${VITE_API_URL}/v1${path}`), which all seven
`*PdfUrl()` functions across the app — the four pre-existing ones plus
this phase's three new ones (`certificatesApi.pdfUrl`,
`idCardsApi.studentPdfUrl`/`staffPdfUrl`) — now go through. The general
lesson: a smoke-test helper that reconstructs a URL manually to work
around a known limitation (here, `page.request`'s cookie/header
handling) can end up silently validating the workaround instead of the
real thing — worth periodically checking that a "known limitation"
workaround hasn't started masking an actual bug in the code it was
written to route around.

**Phase 12 (Reports & Analytics, global search, dashboard completion —
the last domain-feature phase) surfaced one real bug, and it was the
single most on-the-nose kind: the smoke test typed the exact label the
app displays for a person's name, and got zero results back.**

The browser run (9/9 after the fix) covered three sub-features in one
script: School Admin's dashboard confirmed the new staff widgets
(pending leave, fee collection, overdue books); a Super Admin login
confirmed the new cross-tenant `role_context: 'super-admin'` branch
renders school-count stats instead of the old fallback-to-staff-summary
gap `docs/roadmap.md` had flagged since Phase 6; a student login
confirmed the stale "Homework and exams are coming soon" placeholder —
present, unnoticed, since Phase 0 — is finally gone, replaced by real
pending-homework/upcoming-exam counts; all four report pages
(attendance, academic performance, enrollment, operations) loaded and
rendered a `recharts` chart where one made sense; and global search was
driven end to end through the actual UI — type in the Topbar search box,
see a result, click it, land on the right page.

That last step is where it broke. Typing "Sam Student" — literally the
`full_name` the app renders everywhere for that seeded demo student —
into the search box returned "No results," even though every other
single-word query (`"Sam"`, `"Student"` alone) worked fine. The bug was
in `SearchService`'s name matching: `first_name LIKE '%{query}%' OR
last_name LIKE '%{query}%'` treats the *entire* query as one substring
to find in *one* column. "Sam Student" isn't a substring of `first_name
= "Sam"` (missing " Student") and isn't a substring of `last_name =
"Student"` (missing "Sam ") — so a two-word full-name query, the single
most natural thing a user would ever type into a people-search box,
matched nothing at all, silently. A first pass at debugging this
suspected the usual `page.request` cookie-forwarding gap documented
above, since the earlier symptom looked similar (a plausible-but-wrong
result with no error) — but the screenshot showed the search dropdown
itself rendering "No results for 'Sam Student'," a real, fully-authenticated
UI response, not a request-layer artifact. Fixed by splitting the query
into words and requiring every word to independently match
`first_name` OR `last_name` (so "Sam" can hit one column while "Student"
hits the other, in either order) — applied to all three
name-searched categories (students, guardians, staff), plus a new
regression test (`test_search_finds_a_student_by_full_first_and_last_name`)
asserting a two-word query actually finds the record it obviously
should. The general lesson: when smoke-testing a search feature, always
include a query shaped like what a *real* user would actually type for
the exact entity the UI itself displays — a single seed-data first name
is the easy case that a broken multi-word matcher will pass anyway.

**Phase 13 — no browser smoke test (backend-only hardening), but the same
lesson showed up anyway.** Before Phase 13 formally started, actually
using real Stripe test-mode keys for the first time (rather than the
inert/placeholder state they'd been in since Phase 6) immediately broke
self-service signup: `SubscriptionService::createCheckoutSession()`
crashed with `School::newSubscription(): Argument #2 ($prices) must be
of type array|string, null given`. Every Phase 6 automated test and its
"live-verified end to end" manual pass had used plans with no real
`stripe_price_id` — a gap that only surfaces once a real Stripe account
actually tries to create a real Checkout Session against them. Fixed by
creating real (test-mode) Stripe Products/Prices via the API and wiring
their IDs onto the `plans` table and `PlanSeeder`. No automated test
covers this path (it requires live Stripe API calls), so it's recorded
here as a reminder: a `null`-but-technically-valid foreign key/ID column
is invisible to every test that never exercises the third-party call
which actually dereferences it.

**Phase 16 (Unified Multi-Component Examination & Results System) needed
the same discipline twice over: first for the automated-test gate itself,
then for the live pass — and each layer caught something the one before
it couldn't.** After the six sub-phases were implemented, a check of
`git diff --stat -- tests/` showed only two pre-existing test files had
actually been touched — none of the new functionality (negative marking,
the Excel importer, auto-submit, group publish/unpublish masking) had
real coverage despite the plan's own stated test gate per sub-phase.
Writing that missing coverage surfaced two real bugs before a browser was
ever involved: `ExamController::publishGroup()`/`unpublishGroup()` built
their response from a `SubjectResultResource`, which requires a
`student_id` — but publishing a subject is a class-wide action with no
single student in view, and the frontend never sent one, so every real
"Declare Result" click would have 404'd. And `McqQuestionsImport`'s
`rules()` rejected purely numeric-looking option text (`"3"`, `"4"`,
`"5"`, `"6"` — an entirely ordinary Math MCQ) because PhpSpreadsheet reads
a numeric-looking cell back as an actual int/float, not a string, and the
validation rule required `'string'`; the importer's own `onRow()` already
defensively `(string)`-cast everything, but never got the chance to run.
Both fixed before any of this reached a browser.

The live pass then needed real MySQL, not just SQLite, before it could
even start: `php artisan tenants:migrate` against the actual dev tenant
databases failed three separate times with errors no SQLite-backed test
run could ever produce — dropping a composite unique index still backing
a foreign key, changing a column to NOT NULL while its FK still specified
`SET NULL` on delete, and an auto-generated index name over MySQL's
64-character identifier limit. See
[database.md § Exams, grading & online tests](database.md#exams-grading--online-tests)
for the fixes; the general lesson is its own reminder: **a schema-changing
phase isn't done when `php artisan test` is green — it's done when the
migration has actually run against a real database of the engine
production uses.**

Only then did the actual UI walkthrough begin — reproducing the user's
own worked example (Admin/Class Teacher builds a Maths subject with four
components: Online MCQ 17/20, Written 48/60, Oral 8/10, Practical 9/10;
declares just that one subject early; Student and Parent portals confirm
nothing before declaration and the full 82/100, 82%, Pass breakdown after)
— and it surfaced two more bugs neither the sub-phase tests nor the newly
-written coverage above had caught, both invisible to any test that
provisions its own fully-permissioned fixture from a factory: (1) the
demo tenant (`civisence`) had been provisioned *before* Phase 16 shipped,
so its already-existing Class Teacher role never picked up the new
`exam-marks.publish`/`questions.import` permissions — the "Declare
Result" button silently never rendered, the same "forgot to re-sync an
already-provisioned tenant's permission matrix" gap this project has now
hit in Phases 7, 8, 10, and 16. Fixed by running `php artisan
permissions:rollout`, per its own established convention. (2) A real,
more serious bug: `Exam::scopeVisibleTo()`'s Student and Parent branches
still gated visibility on the flat `is_published` column alone, never on
a subject group's own `published_at` — `ParentPortalController::
childExams()` had the correct OR-composition (written earlier in the same
phase), but this shared scope, which the student's *own* "By Exam"
dropdown actually goes through, was never updated. A student could
therefore never see an exam in their own results view until the *entire*
exam was published, silently defeating the whole early-declare feature
for the one portal it was built for. No API-level test caught it because
`report-card` tests all call that endpoint directly with an already-known
exam id — never through the listing step a real dropdown depends on.
Fixed, with a new regression test
(`test_student_can_list_an_exam_once_their_own_sections_subject_group_is_published`)
added specifically because the gap existed at the *listing* layer, not
the one every other Phase 16 test had been exercising. The general lesson
holds precisely as stated after Phase 5: automated tests confirm the code
does what you told it to; only driving the actual dropdown a real student
clicks confirms you told the right layer to do it.

Phase 13 itself added `tests/Feature/Security/SecurityHeadersTest.php`
(3 tests: baseline headers present, HSTS absent over plain HTTP, HSTS
present over HTTPS) — full suite now 248/248. The HTTPS-simulation test
needed one iteration: passing `server: ['HTTPS' => 'on']` to a *relative*
`/api/v1/plans` URI didn't flip `Request::isSecure()`, because
`prepareUrlForRequest()` resolves relative URIs against `APP_URL`
(`http://…` in testing) before Symfony's `Request::create()` ever sees
the server vars, and the URL's own scheme wins. Fixed by calling an
absolute `https://localhost/...` URL directly instead of relying on the
server var alone.
