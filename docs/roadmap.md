# Roadmap & Conventions for New Modules

## Status

**Done (Phase 0-3):** foundations, identity & access (auth, 12 default
roles + custom roles, DB-driven settings, audit log), academic structure
(years/terms/departments/grade levels/sections/subjects/rooms/timetable/holidays),
student management (admission, profiles, documents, guardians, full
enrollment lifecycle, bulk import/export), and parent portal basics
(linked-children view, child profile). See [README.md](README.md) for the
full doc set covering this slice in depth.

**Done (Phase 4):** Attendance — student attendance (daily and period-based,
per-section bulk marking with row-level scoping to a Teacher/Class Teacher's
own sections, single-record corrections, percentage summaries), staff
attendance (self check-in/check-out, HR/Admin bulk marking, corrections),
integrated into the Student Profile (Attendance tab), Parent Portal (child
Attendance tab via a dedicated permission-independent endpoint), and
Dashboard (today's-marked-count / this-month's-percentage widgets per role).
See [api.md § Attendance](api.md#attendance),
[rbac.md](rbac.md#permission-catalogue), and
[database.md](database.md#key-modeling-decisions) for the `date`-column
gotcha this phase surfaced and fixed everywhere it appeared.

**Done (Phase 5):** Examinations — configurable grading scales/bands,
offline exams with per-subject marks entry (upsert-not-replace so
re-editing an exam's subject list never cascades-deletes entered marks),
per-exam report cards (screen + PDF, staff and parent), a full online
examination system (MCQ/True-False question bank, timed auto-graded
tests with autosave, per-attempt result breakdown), and a consolidated
term/year result view with per-exam weighting and section-rank, wired
into the Student Profile, Parent Portal, and a dedicated Student nav
(students previously shared the Parent's nav by mistake — fixed here).
See [api.md § Grading & Examinations](api.md#grading--examinations),
[rbac.md](rbac.md#permission-catalogue), and
[database.md](database.md#key-modeling-decisions) for the schema and
permission model. This phase's manual smoke test (again) found real bugs
no automated test caught: `useCrudResource`'s cache-invalidation call
used the wrong query key shape and silently never refreshed any list
after create/update/delete across all 13 pages using it (fixed to
invalidate by resource-name prefix); `ExamController::show()` crashed
with a raw 500 on a non-numeric ID instead of a clean 404 (fixed with a
route-level numeric constraint); and the parent-facing exam tab was
missing the consolidated term-result view entirely even though the
backend endpoint for it already existed (fixed by mirroring the
staff-facing tab). See
[testing.md § Manual/browser smoke testing](testing.md#manualbrowser-smoke-testing).

**Done (Phase 6):** SaaS Platform Layer — turning the app from an
internally-provisioned multi-school system into a real, sellable
multi-tenant SaaS: self-service school signup, real Stripe subscription
billing, plan-based seat limits, and a platform-owner admin console.
Built in five sub-phases (A: data model + provisioning service, B:
Stripe/Cashier integration, C: self-service signup, D: platform admin
console, E: plan-based seat limits), summarized below as each lands.
This phase deliberately paused the domain-feature roadmap (old Phase
6-12, renumbered 7-13 below) at the user's request, since getting the
platform sellable matters more right now than adding more
school-management modules.

**Sub-phase A done — data model + provisioning service.** New `plans`
table (platform-level, seeded with placeholder Starter/Growth/Scale
tiers) and billing columns on `schools` (`plan_id`, `stripe_id`,
`pm_type`, `pm_last_four`, `trial_ends_at`, `billing_status` — the last
storing Stripe's own raw status strings directly, no custom enum
translation layer). New `SchoolProvisioningService` extracted the
per-school role/permission-matrix logic out of `RolePermissionSeeder`
(which now just delegates to it) into a real, transactional, injectable
service — the seam both the CLI seeder and the upcoming self-service
signup endpoint share, so there's exactly one place that knows how to
stand up a tenant. Existing pre-SaaS schools were backfilled to
`billing_status = 'active'` in the same migration (they were never
meant to pay) so nothing already running breaks once later sub-phases
add access gating. See [database.md](database.md#key-modeling-decisions).

**Sub-phase B done — Stripe/Cashier integration.** `laravel/cashier`
installed with `Billable` on `School` (billing is per-school, not
per-user — a founding admin can leave, the school keeps paying).
Cashier's published `subscriptions` migration ships with a hardcoded
`user_id` column; renamed to `school_id` to match, since
`Subscription::owner()`/`Billable::subscriptions()` both resolve the FK
column dynamically via `getForeignKey()` on whichever model
`Cashier::useCustomerModel()` points at, not literally `user_id`.
`StripeWebhookController extends Cashier's WebhookController`, syncing
`schools.billing_status` on top of Cashier's own `subscriptions`-table
sync (subscription created/updated/deleted, invoice payment
succeeded/failed with a `past_due` grace period — not an immediate lock,
Stripe's own Smart Retries run first — and a trial-ending-soon
notification). `Cashier::ignoreRoutes()` + manual registration in
`routes/web.php` (not `api.php` — Stripe calls this server-to-server, it
needs neither the API group's Sanctum stack nor CSRF, just
`VerifyWebhookSignature`) at `/stripe/webhook` and `/stripe/payment/{id}`,
keeping Cashier's own route names so `php artisan cashier:webhook` and
the incomplete-payment flow keep working unmodified. Covered by 9 tests
posting hand-built payloads directly at the webhook route (safe without
a real Stripe signature since `STRIPE_WEBHOOK_SECRET` is explicitly
blanked in `phpunit.xml`, not just left unset — see below), **plus live
verification against a real Stripe test-mode account**: real Checkout
Session creation, a real trial subscription driven through
`stripe listen`-forwarded webhooks, `SubscriptionService::swapPlan()`
and `cancel()` both confirmed against the live subscription. Live
testing caught one real bug no hand-built-payload test could: the
`invoice.payment_succeeded` handler unconditionally set
`billing_status = 'active'`, but a $0 trial-setup invoice can succeed
while the subscription itself is still legitimately `'trialing'` — the
two webhook events arrive together for a new trial subscription, and
the invoice handler was racing ahead of and overwriting the correct
status the `customer.subscription.created` handler had just set. Fixed
to mirror the linked subscription's own current status instead of
assuming `'active'`. A second, unrelated bug surfaced by the same live
session: the webhook tests had been silently relying on `.env`'s
`STRIPE_WEBHOOK_SECRET` happening to be blank rather than isolating the
test environment explicitly — the moment a real secret was added to
`.env` for live testing, all 9 tests started failing on signature
verification. Fixed by adding an explicit
`<env name="STRIPE_WEBHOOK_SECRET" value=""/>` to `phpunit.xml`,
matching the explicit-override pattern already used there for
`MAIL_MAILER`/`QUEUE_CONNECTION`/etc., so the test suite no longer
depends on what happens to be in a developer's local `.env`.

**Sub-phase C done — self-service signup, live-verified end to end in a
real browser against real Stripe.** `POST /auth/signup` (public,
`throttle:5,60`) provisions the tenant via `SchoolProvisioningService`,
logs the new admin in immediately (before Stripe is even touched — a
Checkout redirect is a payment step, not a login gate), sends email
verification, then creates a Stripe Checkout session and returns its
URL; on Checkout-creation failure it compensates by soft-deleting the
just-created school+admin rather than leaving an orphaned, unbillable
tenant. `GET /plans` is the public pricing-page endpoint (deliberately
excludes `stripe_product_id`/`stripe_price_id` from the response — no
reason to expose Stripe object ids to an anonymous visitor).
`UserResource` gained a `school` snapshot (`billing_status`,
`trial_ends_at`, plan name), included wherever a controller eager-loads
the `school.plan` relation (`/auth/login`, `/auth/me`, signup's own
response) — this is what lets the frontend detect the webhook-driven
trial activation without a dedicated endpoint. Frontend: a new
`PublicLayout` (static platform branding, not `ThemeContext`'s
tenant-resolved one — there's no tenant yet), a 3-step `SignupPage`
wizard (school → admin → plan, react-hook-form + zod, following
`StudentAdmissionPage`'s convention) that hard-navigates to Stripe's
hosted Checkout on submit, and a `SignupCompletePage` (inside the
authenticated shell) that polls `/auth/me` until the webhook flips
`billing_status` and then auto-redirects to the dashboard. Backend
tests mock `SubscriptionService` (a real `Laravel\Cashier\Checkout`
built locally via `Stripe\Checkout\Session::constructFrom()`, not a
duck-typed stand-in — Mockery enforces the real declared return type).
**Then verified live**: a scripted browser run through the actual
wizard, a real Stripe Checkout page (card `4242 4242 4242 4242` +
billing address), webhook-driven activation, and landing on a fully
permissioned dashboard as the new School Admin — 9/9 steps passed on
the second attempt (the first correctly caught that Stripe's hosted
page has its own client-side required-field validation the script
hadn't filled).

**Sub-phase D done — platform admin console, Super Admin only,
cross-tenant.** New `Api\V1\Platform\` controllers (`PlatformSchoolController`
index/show, `SchoolPlanController` for changing a school's plan —
reusing the exact same `SubscriptionService::swapPlan()` a future
School-Admin-facing "change plan" action will also call, so Stripe and
the local plan/limits projection can never drift depending on who
initiated the change — and `PlatformMetricsController`), authorized
directly via the new `platform.*` permission strings
(`view-tenants`/`manage-billing`/`view-metrics`) rather than a Policy
class — Spatie auto-registers every permission name as a Gate ability,
and there's no single `School` instance to check "view" against for an
index of all of them. `School` gained `studentCount()`/`staffCount()`
methods for the usage-vs-limit display; `staffCount()` queries
`model_has_roles` directly with an explicit `school_id` filter rather
than the `roles()` relation, which would otherwise depend on the
*current* global Spatie team context — reading another school's staff
count from the platform console must not mutate that shared context.
"Staff seat" excludes Student/Parent roles (see this phase's
Recommended-defaults section). Frontend: a `PLATFORM_ADMIN_NAV_GROUPS`
nav section shown only when `school_id === null`, via a new
`resolveNavGroups()` helper both `Sidebar.tsx` (desktop) and
`AppShell.tsx` (mobile drawer) now call — replacing two separate,
already-diverged copies of the same role-to-nav-group logic (the
mobile one incorrectly gave Students the Parent nav; fixed as part of
unifying them, confirmed live at a narrow viewport). Three pages
(schools list, school detail with plan-change UI, metrics stat tiles).
**Live-verified in a real browser**: Super Admin sees Platform nav and
not the regular one; a School Admin sees neither the nav item nor can
reach the routes directly (`PermissionRoute` → "Access denied"); a
live plan change updates Stripe-adjacent state end to end; metrics
tally correctly across three seeded test tenants in different billing
states. One pre-existing, out-of-scope quirk noticed along the way and
deliberately not fixed here: the generic `DashboardPage` a Super Admin
lands on at `/` shows a School-Admin-shaped summary (student/staff
counts) that doesn't really make sense with no single school — a
Super-Admin-specific dashboard view is a reasonable future addition,
not part of what this sub-phase was scoped to build.

**Sub-phase E done — plan-based seat limits, completing Phase 6.** A new
`PlanLimitService` (`assertCanAddStudent`/`assertCanAddStaffUser`) counts
current usage against the school's `billing.max_students`/`max_staff`
Settings projection (see [database.md](database.md#key-modeling-decisions))
and throws `PlanLimitExceededException` — rendered as HTTP 402, kept
distinct from 422 (validation) and 403 (permission) so the frontend can
react consistently. Wired in at the three places a school actually grows
its usage: `StudentController::store`, `StudentImportController` (checked
once against current usage before the import runs — a hard
"you're at your limit, upgrade to import more" gate, not a precise
per-row cutoff), and `UserController::store` (only when the requested
roles include something other than Student/Parent, matching this phase's
"staff seat" definition). A new `EnsureSchoolIsUsable` middleware, applied
globally to the API group, enforces the phase's access matrix: Super
Admin is always exempt; `is_active = false` locks the school regardless
of billing status (finally enforcing a previously-inert column);
`canceled`/`unpaid`/`incomplete_expired` lock it too (402); `past_due`
allows reads but blocks writes (402) — a grace period, not an immediate
cutoff, matching Sub-phase B's webhook handling; `null`/`trialing`/`active`
get full access. A small route-name allowlist (`auth.me`, `auth.logout`,
`billing.show`, `billing.portal`) stays reachable even from a fully
locked school, so an admin can always see why they're locked out and fix
it. New `billing.view`/`billing.manage` permissions (School Admin only,
school-scoped — distinct from `platform.*`'s cross-tenant Super Admin
scope) back a new `BillingController` (`GET /billing` reuses
`PlatformSchoolResource` from Sub-phase D unchanged, since a School
Admin viewing their own school needs the identical shape a platform
admin sees for it; `GET /billing/portal` returns a Cashier-hosted
Customer Portal URL, a clean 422 if `stripe_id` is still null rather
than letting Cashier's own exception surface). Frontend: a `BillingPage`
(plan/status/trial stat cards, usage-vs-limit display, "Manage billing"
button that hard-navigates to the portal URL) and a `BillingStatusBanner`
shown app-wide (reads the billing snapshot already on the logged-in user
— no extra request) for locked/past-due/trial-ending-soon states, with
the "Manage billing" link itself gated by `billing.manage` so non-admin
staff don't see a link that would 403. Covered by 151 backend tests
(unit tests for the limit service, feature tests for the middleware's
full access matrix and both billing endpoints, and integration tests
hitting real HTTP endpoints at the limit) and 16 frontend tests, **then
live-verified in a real browser**: a healthy school shows no banner and
a working billing page; a past-due school shows the read-only banner,
can still read data, and gets a clear 402 message on write; a
limit-test school gets blocked from admitting a student past its plan's
cap with the "Upgrade your plan" message. One real application bug
surfaced along the way (not by the browser run itself, but by
`route:list` while wiring the new controller in): `BillingController`
was missing its `use App\Http\Controllers\Controller;` import, so PHP
resolved the bare `Controller` name relative to the current namespace
and fatal-errored — fixed by adding the import. See
[testing.md § Manual/browser smoke testing](testing.md#manualbrowser-smoke-testing)
for a second, test-infrastructure-only lesson this sub-phase surfaced
(not an application bug, but worth the same discipline).

**Done (Phase 7):** Teacher module completion — homework/assignments,
submission tracking, and remarks. New `homeworks`/`homework_submissions`/
`student_remarks` tables and a `homework`/`remarks` permission module
(`homework`: view/create/edit/delete/grade; `remarks`: view/create/edit/
delete). A `Homework` is scoped to one section+subject+teacher (unlike
Exam's two-level Exam/ExamSubject split — an assignment doesn't need a
grouping parent), with `Homework::isTaughtBy()`/`scopeVisibleTo()`
mirroring `ExamSubject`'s exact row-scoping pattern: a Teacher/Class
Teacher may only create/edit/delete/grade homework for a subject they're
actually assigned to teach (`ClassSubjectTeacher`), School Admin is
unrestricted, and Student/Parent only ever see homework touching their
own/their child's current section. A `HomeworkSubmission` is
upserted (not append-only) — resubmitting before grading replaces the
previous content/attachment rather than creating a second row, keyed on
`(homework_id, student_id)`. `StudentRemark` reuses `Student::scopeVisibleTo()`'s
same "sections a Teacher/Class Teacher can act on" set for write access,
and gates Parent visibility behind an explicit `visible_to_guardian` flag
(defaults true) — a Student always sees remarks about themselves
regardless of that flag, since it only controls what a *guardian* sees.
File attachments (teacher's instructions, student's submitted work) reuse
the existing spatie/laravel-medialibrary pattern from student documents.
Frontend: a shared `HomeworkListPage` at `/homework` used by both staff
(create button, roster-and-grade on row click) and Student (submit modal
on row click) — same "one page, role-conditional rendering" pattern
`ExamsListPage` established, rather than a separate "My Homework" page;
a `HomeworkDetailPage` roster view for grading; a new "Remarks" tab on
the Student Profile page and a real "Homework"/"Remarks" tab pair on the
Parent Portal's child profile (replacing the "Homework & Fees"
coming-soon placeholder — Fees stays a placeholder, that's Phase 8).
Covered by 13 new backend tests (164 total) and a live browser smoke
test (8/8) driving the full loop: Teacher assigns homework and writes a
remark → Student submits → Teacher grades from the roster → Parent sees
the graded score/feedback and the remark on their child's profile. One
real bug surfaced along the way, the same shape as Sub-phase E's
`BillingController`: a `HomeworkSubmissionController` missing its
`use App\Http\Controllers\Controller;` import, caught immediately by
`route:list` fatal-erroring rather than by the browser run. See
[testing.md § Manual/browser smoke testing](testing.md#manualbrowser-smoke-testing)
for three more lessons this phase's smoke-testing surfaced — all in the
test script, not the app, but worth the same rigor.

**Done (Phase 8):** Fees/Billing/Accounting — school-to-parent fee
billing, a different concern from Phase 6's platform-to-school
subscription billing (`fees`/`invoices` permission modules, not
`billing`, to avoid collision — this naming was pre-planned back in
Phase 6, see its comments in `config/permissions.php`). New
`fee_categories` (fee heads: Tuition, Transport, ...), `fee_structures`
(templates — amount, frequency, optionally scoped to one grade level),
`student_fee_assignments` (per-student percentage/fixed discounts
against a structure), `invoices`/`invoice_items`, `payments`, and
`credit_notes` tables. Invoice/payment/credit-note numbers reuse
`IdSequenceService` exactly as `StudentIdGeneratorService` does for
admission numbers — its docblock named this as the intended future use
back in Phase 0. A new `App\Contracts\PaymentGatewayInterface` is the
seam between "an invoice got money against it" and how that money was
collected; only a `ManualPaymentGateway` (cash/cheque/bank-transfer/UPI/
card recorded by staff, no external charge) ships in this pass — a real
online gateway is a deliberate future integration, per this doc's own
cross-cutting-decisions section below, not built here. `InvoiceService`
owns every state transition (create, bulk-generate from a structure,
record a payment, issue a credit note, void, recalculate `amount_paid`/
`credit_total`/`status`) so controllers never touch those columns
directly. Bulk invoice generation (`POST
/fee-structures/{id}/generate-invoices`) is idempotent — a student
already invoiced for that exact structure is skipped, not double-billed
— and applies that student's `StudentFeeAssignment` discount if one
exists. `Invoice`/`Payment` reuse Homework's exact
`scopeVisibleTo()` shape for Student/Parent row-level access, with no
Teacher branch (Teacher/Class Teacher never hold `invoices.view` at
all). Frontend: Fee Categories/Fee Structures pages (plain CRUD plus a
"Generate Invoices" bulk action), an Invoices list + detail page (line
items, payment/credit-note history, receipt PDF downloads, void), a Fee
Reports page (collection summary, outstanding dues), a new "Fees" tab
on the Student Profile page, and the Parent Portal child profile's
"Homework & Fees" placeholder finally replaced with a real "Fees" tab
(Phase 7 had already split out "Homework"). Covered by 14 new backend
tests (178 total) and a live browser smoke test (13/13, after fixing
what it found) driving the full loop: Accountant creates a category and
a grade-scoped structure → bulk-generates invoices for one section →
records a partial payment → issues a credit note that brings the
invoice to Paid → downloads a receipt PDF → voids a separate unpaid
invoice → Student sees only their own invoice under a new "My Fees" nav
item → Parent sees the same statement on their child's profile. Four
real bugs surfaced along the way: the Accountant role was missing
`academic-structure.view` (the Generate Invoices modal's grade/section
dropdowns need it, even though "Fees" isn't an academic-structure
page); a "Generate Invoices" section dropdown listed every section
school-wide by bare name, ambiguous whenever two grades share a section
name (fixed to filter to the structure's own grade and qualify the
label otherwise); the void confirmation dialog's button read the
generic default "Confirm" instead of naming the action; and — the
significant one — bulk-generating invoices for more than one student
reproducibly 500'd under `php artisan serve` on Windows
(`SQLSTATE[HY000]: General error: 14 unable to open database file`)
while the identical service call succeeded via Tinker, traced to one
long transaction doing several sequential writes and fixed by making
bulk generation one transaction per student instead of one around the
whole batch. See
[database.md § Key modeling decisions](database.md#key-modeling-decisions)
and
[testing.md § Manual/browser smoke testing](testing.md#manualbrowser-smoke-testing)
for the full diagnosis of that last one — it's a genuinely useful
lesson for any future bulk/multi-write endpoint, not a one-off.

**Done (Phase 9):** Staff/HR — designations, leave management, and
payroll. HR fields (`designation_id`, `employee_id`, `hire_date`) were
added directly to `users` via a purely additive migration rather than a
parallel `staff_profiles` table — "Staff/HR concerns Users, not a new
entity" was the deciding framing. New `designations` (plain lookup CRUD,
structurally identical to `Department`), `leave_types` (config),
`leave_requests`, `salary_structures`, and `payslips` tables. `leave`/
`payroll` each split the same way Phase 4's staff attendance did: a
staff member's own leave requests/payslips are entirely self-service and
unpermissioned (no permission even exists for "apply for your own
leave" or "view your own payslip," matching how "self-check-in" was
never a `staff-attendance.*` permission either), while `leave.view`/
`payroll.view` only ever expand reach to *other* staff's records —
`LeaveRequestController`/`PayslipController` copy `StaffAttendanceController`'s
exact index-scoping shape. Approving a `LeaveRequest` stamps every day
in the range as `on_leave` on the staff member's `staff_attendances` —
an enum value Phase 4 shipped but nothing wrote automatically until now
(`AttendanceService::markOnLeave()`, reusing that class's own
`whereDate()`-safe upsert helper so an already-marked day is corrected,
not duplicated). Payroll mirrors Phase 8's Fee/Invoice shape closely: a
`SalaryStructure` is the recurring template (creating a new one for a
user closes the previous one rather than deleting it — a past
`Payslip` must still resolve the structure that was actually in effect
when it was generated), and bulk `Payslip` generation snapshots each
active structure's amounts, is idempotent per `(user_id, month, year)`,
and — applying Phase 8's hard-won lesson from the start this time
instead of rediscovering it — runs one transaction per staff member,
never one around the whole batch. `payroll.*` is deliberately withheld
from Principal/Management by default (unlike `fees`/`invoices`, which
they get read access to) since salary data carries a confidentiality
expectation attendance/leave data doesn't. Frontend: standalone
Designations/Leave Types/Leave Requests/Salary Structures/Payslips
pages under a new "HR & Payroll" nav group — deliberately *not* a tabbed
"Staff Profile" page, since no such detail page exists for `User` today
(Staff Attendance itself never got one in Phase 4, either) and inventing
one just for this phase would have been a bigger, separate decision; the
`UserFormModal` gained designation/employee-ID/hire-date fields instead.
Leave Requests and Payslips are role-conditional single pages (any staff
member sees their own; HR/Admin additionally sees everyone's plus
approve/generate/mark-paid actions) — the same "one page, role-conditional
rendering" pattern `HomeworkListPage`/`ExamsListPage` established.
Covered by 18 new backend tests (193 total) and a live browser smoke
test (13/13, after fixing what it found) driving the full loop: HR
creates a designation and leave type, assigns both to a teacher via the
Users page, creates a salary structure → teacher applies for their own
leave and confirms they see only their own requests → HR approves it,
confirmed via the API that the teacher's staff attendance now shows
`on_leave` for those dates → HR generates payroll for the month,
downloads the payslip PDF, marks it paid → teacher confirms they see
only their own paid payslip with no HR-only actions → Principal
confirmed blocked from Salary Structures entirely. Two real application
bugs surfaced along the way: `LeaveTypePolicy::viewAny()` required
`leave.view`/`leave.manage`, 403ing the leave-type dropdown for the
exact Teacher self-service flow the module exists for (config was
accidentally gated more strictly than the self-service action depending
on it); and `useCrudResource`'s three mutations had no `onError`
handler at all, so any page using the hook without its own try/catch
(most of them) failed a mutation completely silently — no toast, modal
left open, zero indication anything went wrong — fixed once, centrally,
rather than patched per page. See
[testing.md § Manual/browser smoke testing](testing.md#manualbrowser-smoke-testing)
for the full debugging trail on both, including a `text=` false-positive
(a still-open form's own Select value satisfying a page-wide text
match) that took real digging to distinguish from several
plausible-but-wrong culprits first.

**Done (Phase 10):** Library, Transport, Hostel, and Visitor Management/
Front Desk — four independent sub-modules sharing one permission shape.
New `books`/`book_issues`, `vehicles`/`routes`/`route_stops`/
`student_transport_assignments`, `hostels`/`hostel_rooms`/
`hostel_allocations`, and `visitors` tables, each behind its own
`library`/`transport`/`hostel`/`front-desk` permission module — every one
just `view`/`manage`, unlike every prior module's four-or-more-verb shape.
That regularity earned a new shared `BaseViewManagePolicy` (mirroring the
hand-written `GradingScalePolicy` from Phase 4-5, generalized now that ten
entities at once needed the identical two-permission shape), with each
concrete policy reduced to a one-line `protected string $permissionPrefix`.
`StudentTransportAssignment` and `HostelAllocation` reuse `SalaryStructure`'s
exact "close the previous active row, don't delete it" pattern for
one-active-record-per-student; `HostelService::allocate()` additionally
enforces room capacity (`HostelRoom::occupiedCount()` against `capacity`)
before creating the new allocation, both inside one transaction.
`IssueBookRequest` is this phase's one new validation shape: exactly one of
`student_id`/`user_id` via `required_without` + `prohibits` (a portable
alternative to a DB-level "exactly one of two nullable FKs" constraint,
which isn't expressible identically across SQLite and MySQL). `BookIssue`
fine calculation (`LibraryService::calculateFine()`) clones the due date
before mutating it with `startOfDay()` — Carbon mutates in place by
default, and the un-cloned call would have corrupted the live model's own
`due_date` attribute. Frontend: four new nav groups (Library/Transport/
Hostel/Front Desk), nine pages total — plain CRUD pages for Books/
Vehicles/Hostels/Hostel Rooms, and four custom action pages (Book Issues
with Issue/Return, Routes with inline nested-stop management, Student
Transport Assignments and Hostel Allocations each with an assign/allocate
modal plus a terminal action, Visitors with check-in/check-out) following
the same "list page + verb buttons, no separate detail page" shape
Phase 8/9's non-CRUD pages established. Covered by 19 new backend tests
(212 total) and a live browser smoke test (15/15, after fixing what it
found) driving all four modules in one run: Librarian creates a book,
issues it to a student, confirms available-copy count drops and recovers
on return; Transport Staff creates a vehicle and a route with two stops,
assigns a student to both; HR Staff creates a hostel and room, allocates
and then vacates a student; Receptionist logs and checks out a visitor.
One real application bug surfaced by the smoke test, not by any automated
test: the Librarian, Transport Staff, and HR Staff roles were never
granted `students.view`, so every one of this phase's "pick a student"
dropdowns (issue a book, assign transport, allocate a hostel room) came
back empty — each role needs read access to the student list to do the
job its own module exists for, the same reasoning that already justified
`students.view` on the Receptionist role. Fixed by adding it to all three
roles' grants and re-running `RolePermissionSeeder` against the demo
school. A smaller bug was caught by direct inspection while building,
before it ever reached a browser: `HostelResource` used
`whenCounted('rooms')` but the controller only ever calls `with('rooms')`,
never `withCount()`, so the field would always have rendered `null` —
fixed to `whenLoaded('rooms', fn () => $this->rooms->count())`. See
[testing.md § Manual/browser smoke testing](testing.md#manualbrowser-smoke-testing)
for the smoke test's full step list.

**Done (Phase 11):** Certificates & ID Cards, Notice Board (notices and
events in one feed), and Communication/Notifications — three sub-features
unified by one new in-app notification inbox. New `certificate_templates`/
`certificates`, `notices`, `announcements`/`app_notifications` tables. ID
cards are deliberately *not* a stored entity — `IdCardController::student()`/
`staff()` render a PDF on demand straight from the `Student`/`User` row,
reusing the `uuid` column Phase 0 already carried "for QR codes in a later
phase" (see [database.md](database.md#key-modeling-decisions)) via a new
`QrCodeGenerator` support class (`endroid/qr-code`, embedded as a base64
data URI so dompdf never needs a network round-trip for the image). A
`Certificate`, by contrast, is a real issued record — `CertificateService::issue()`
snapshots a `CertificateTemplate`'s body with its placeholders
(`{{student_name}}`, `{{admission_number}}`, ...) replaced and a
sequential `certificate_number` (the same `IdSequenceService` +
settings-driven-format pattern as invoice/payslip numbers), so a later
template edit never rewrites an already-issued certificate. All three
Phase 10 sub-modules' `view`/`manage` permission shape didn't fit here as
cleanly as it first looked: `certificates` ended up needing the fuller
`view/create/edit/delete/issue` shape (template CRUD is genuinely
multi-verb, with `issue` bolted on the same way `invoices.void` was),
while `notice-board` (`view/create/edit/delete/publish`) and
`communication` (`view/manage`) split the other way. The notice board
itself is deliberately *not* permission-gated for reading — like homework
submission or self check-in, browsing published notices needs no
permission at all (`NoticePolicy::viewAny()` is unconditionally `true`);
`notice-board.view` only ever expands reach to *draft* notices, granted
just to School Admin/Principal/Management for oversight. `Announcement`
(a staff-composed broadcast) and `AppNotification` (one inbox row per
recipient) are two different models on purpose — composing and sending is
an audited, staff-only action with its own history page, while reading
your own inbox is self-service and permission-free, the same split
Phase 9 drew between a `LeaveRequest` and who gets to see whose. Sending
resolves recipients by role (`students`/`staff`/`parents`/`all`) and fans
out three ways: an `AppNotification` row always (backs the new bell-icon
inbox in the Topbar, present in every layout), a real `Mail::to()->send()`
call when the `email` channel is picked (using Laravel's already-stock,
if not-yet-production-configured, mail support — no new mail
infrastructure needed), and calls through two brand-new seam interfaces,
`SmsGatewayInterface`/`PushGatewayInterface`, when `sms`/`push` are
picked. Only a `LogSmsGateway`/`LogPushGateway` (writes to the Laravel
log, no external delivery) ship in this pass — deliberately mirroring
`PaymentGatewayInterface`'s Phase 8 precedent exactly: a real third-party
provider (Twilio, FCM, ...) needs real credentials and a real per-message
cost, so it's a future integration, not something to guess at here. All
of this runs synchronously, not queued — this codebase has no queue
worker running yet, so dispatching a queued job would have silently gone
nowhere rather than actually sending anything; queueing it is a
reasonable future hardening step once a worker exists, not a gap in this
phase's own scope. Frontend: a `NotificationBell` in the Topbar (present
app-wide, not a page — polls every 60s, shows an unread badge, marks read
on click), Certificate Templates/Certificates pages, a card-based Notice
Board page (unlike every other module's `DataTable`, since notices are
prose-and-metadata, not tabular data — role-conditional the same way
Leave Requests/Payslips are: everyone reads, only `notice-board.*` holders
see create/publish/delete), and an Announcements page (compose modal +
sent history). Covered by 19 new backend tests (231 total) and a live
browser smoke test (12/12, after fixing what it found) driving all three
sub-features in one run. Two real application bugs surfaced along the
way, both caught before reaching the browser: the certificate PDF's Blade
view had a `<table class="meta">` where `.meta`'s own CSS declared
`display: flex`, and dompdf's table-layout engine doesn't tolerate a
`<table>` losing its table formatting context — it crashed with "Parent
table not found for table cell" the moment a real `<td>` rendered inside
it; fixed by giving the table its own, non-conflicting class. Separately,
`NoticeController::store()` returned the freshly created notice via
`->load(self::WITH)`, but `is_published` isn't part of the create
payload at all (a new notice always starts unpublished) — `load()` only
eager-loads relations, it doesn't re-fetch scalar columns, so the
in-memory model's `is_published` stayed PHP `null` instead of picking up
the migration's `default(false)`, and the create response rendered `null`
instead of `false`; fixed by using `->fresh(self::WITH)` instead, which
actually re-reads the row. A third, genuinely pre-existing bug (not
introduced by this phase, but found while building it) is worth its own
line: every `*PdfUrl()` helper across the whole app (`fees.ts`, `hr.ts`,
`exams.ts`, `dashboard.ts` — going back to Phase 8) returned a bare
relative path like `/payslips/1/receipt/pdf`, used directly as an
`<a href>`. With no dev-server proxy bridging the SPA's own origin
(`:5173`) and the API's (`:8000`), a real click on any of those download
links would 404 against the SPA's own router instead of ever reaching the
backend — invisible to every prior phase's smoke test because none of
them actually clicked the link, only checked the route didn't 500 via a
manually-reconstructed URL. Fixed with one new shared helper
(`apiFileUrl()`) that all seven functions now go through, so every
PDF/receipt/report-card/ID-card download link in the app resolves to a
real, absolute, working URL. See
[testing.md § Manual/browser smoke testing](testing.md#manualbrowser-smoke-testing)
for the smoke test's full step list, including how it worked around
Playwright's `page.request` not carrying Sanctum's stateful-auth Referer
check by default.

**Done (Phase 12):** Reports & Analytics, global search, and dashboard
completion for every role — the last domain-feature phase before
Phase 13's security/deployment pass. Four new report endpoints
(attendance, academic performance, enrollment, operations) and one new
`GET /search` endpoint, all deliberately **without** a new `reports`/
`search` permission module: a report is just a different shape of "view"
for data the caller can already see, so each endpoint reuses the exact
permission of the module it reports on (`student-attendance.view`/
`staff-attendance.view` for the attendance report, `exam-marks.view` for
academic performance, `students.view` for enrollment, `library.view`/
`transport.view`/`hostel.view` for operations — each section of the
operations/attendance reports independently null unless the caller holds
that specific permission, same "compute only what you're allowed to see"
shape `OperationsReportService`/`AttendanceReportService` share). Global
search works the same way — no permission gates the endpoint itself
(reachable by any authenticated user), each of its five categories
(students, guardians, staff, books, invoices) is simply omitted from the
response if the caller lacks that category's own `.view` permission,
mirroring the Notice Board's "the gate is permissive, the *content* is
scoped" pattern from Phase 11. `DashboardService` finally got its
Phase 6-flagged gap closed — a Super Admin (`school_id === null`,
`Super Admin` role) now gets a real `role_context: 'super-admin'` branch
(cross-tenant school/trial counts) instead of silently falling through to
the School-Admin-shaped `staffSummary()` — and every existing role's
summary grew a few real, permission-gated widgets fed by phases that
shipped after the dashboard was first built (pending leave requests and
this month's fee collection for staff holding `leave.manage`/
`invoices.view-reports`, overdue-book count for `library.view`, a
teacher's own pending-grading count, a student's pending-homework/
upcoming-exam counts replacing a stale "coming soon" placeholder that
had survived, untouched, since Phase 0). Frontend: `recharts` — installed
since early on but never actually used (`FeeReportsPage` predates it and
still renders plain lists) — finally renders real line/bar charts on the
three new report pages that have a time or category dimension worth
visualizing (attendance trend, academic performance, enrollment trend);
Operations Report stays stat-cards-only, matching `FeeReportsPage`'s
existing non-chart precedent, since there's no trend to chart. A new
`GlobalSearch` component sits in the Topbar app-wide (not a page — same
"present everywhere, not routed" shape `NotificationBell` established in
Phase 11), debounced, grouped by category, each result navigating
straight to the matching detail page where one exists (student profile,
invoice detail) or its list page otherwise (guardians/staff/books have no
per-record detail page yet). Covered by 13 new backend tests (245 total)
and a live browser smoke test (9/9, after fixing what it found) driving
all three sub-features: School Admin's and a Super Admin's dashboards,
a student's dashboard confirming the stale placeholder is gone, all four
report pages, and global search end to end (search → click a result →
land on the student's profile). One real, and fairly significant,
application bug surfaced by the smoke run: `SearchService`'s name
matching did a plain `first_name LIKE '%{query}%' OR last_name LIKE
'%{query}%'` — which silently returns *nothing* for the single most
natural way to search for a person, a full "First Last" name, since
neither column contains both words. Typing "Sam Student" (the exact
label the app itself displays everywhere) returned zero results even
though the record obviously existed. Fixed by splitting the query into
words and requiring every word to match first_name-or-last_name
independently (`Sam` hits `first_name`, `Student` hits `last_name`,
both required, order-independent) — applied to all three name-searched
categories (students, guardians, staff). Backed by a new regression test
asserting a full-name query actually finds the record. See
[testing.md § Manual/browser smoke testing](testing.md#manualbrowser-smoke-testing)
for the smoke test's full step list.

With Phase 12 done, every item in the original Phase 6-12 domain roadmap
has shipped. What remains is Phase 13 below — hardening and deployment,
not new end-user-facing modules.

**Done (Phase 13):** Security hardening pass + deployment guide
refinement, scoped in detail once reached (per the note above) rather
than upfront. The existing security posture — CSRF, React's default XSS
escaping, Eloquent-only queries, Policy-backed authorization, per-route
auth throttles, medialibrary upload validation, activity-log audit
trail, bcrypt hashing — was already solid from Phase 0-3 onward; this
pass filled the gaps a real production deploy would actually hit:

- **Security response headers** — `App\Http\Middleware\SecurityHeaders`
  (new), appended globally: `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`, a restrictive `Permissions-Policy`, and
  `Strict-Transport-Security` gated on the request already being HTTPS
  (sending it over plain HTTP would do nothing but confuse local dev).
  No CSP here — this is a JSON API with no server-rendered HTML; CSP
  belongs at the SPA's serving layer instead (see
  [deployment.md § 4](deployment.md#4-reverse-proxy-topology)).
- **General API rate limiting** — a `throttle:api` backstop (150
  req/min per user/IP, named limiter in `AppServiceProvider::boot()`)
  now covers every endpoint, not just the handful with their own
  explicit throttle (login/signup/password-reset). Skipped in the
  `testing` environment on purpose: `phpunit.xml`'s `array` cache store
  persists for the whole test process, so a real limit would accumulate
  across all 248 tests sharing an IP/user rather than resetting per
  test and fail the suite on unrelated runs — the same reasoning
  already applied to the password policy below.
- **Trusted proxies** — unset by default (`TRUSTED_PROXIES` env,
  `bootstrap/app.php`); a reverse-proxy deployment must opt in
  explicitly or `X-Forwarded-*` headers are ignored, which is the safe
  failure mode (misdetected client IP/scheme) rather than the unsafe one
  (trusting headers any client could spoof).
- **Password policy** — production-only (`AppServiceProvider::boot()`,
  gated on `app()->isProduction()`): min 10 chars, mixed case, numbers,
  and Have I Been Pwned breach-checking via `uncompromised()`. Gated
  rather than global because `uncompromised()` makes a live network call
  per submission — a real cost in tests and local dev for no benefit
  there. Local/testing keep Laravel's bare `min(8)`.
- **Dependency audit** — `composer audit` and `npm audit` both clean at
  time of writing; added to the pre-deploy checklist as a recurring
  check, not a one-time pass, since new CVEs surface after code is
  written.
- **Deployment guide** — added a Stripe-specific production section
  (live-mode keys, live Products/Prices, registering a real webhook
  endpoint instead of `stripe listen`) and trusted-proxy guidance to
  [deployment.md](deployment.md), which had covered general
  environment/MySQL/reverse-proxy/queue/mail/backup hardening since
  Phase 0-3 but predated Phase 6's Stripe integration entirely.

See [architecture.md § Security posture](architecture.md#security-posture)
for the consolidated list and [deployment.md](deployment.md)'s pre-deploy
checklist for what's still a manual step at deploy time (real `APP_KEY`,
live Stripe keys, HTTPS termination, backups) — none of that is
something application code can enforce for you.

**Done (Phase 14):** Database-per-tenant multi-tenancy conversion. The
original architecture (Phase 0-13) was row-level: one shared database,
every tenant table carrying a `school_id` column, a global Eloquent scope
enforcing isolation. This phase converted the entire application off that
model to true database-per-tenant — each school gets its own physically
separate database (`stancl/tenancy`), identified by subdomain
(`{school-slug}.{central-domain}`), with a landlord (central) database
holding only genuinely cross-tenant data (schools, plans, billing,
platform admins). Not an incremental migration — a clean cutover across
every model, migration, controller, test, and the frontend's entire auth/
routing assumptions. See [architecture.md § Multi-tenancy](architecture.md#multi-tenancy)
and [§ Authentication](architecture.md#authentication) for the resulting
shape, and [deployment.md § 4](deployment.md) and
[§ 10](deployment.md) for what changed operationally (wildcard DNS/TLS,
per-tenant backups, N+1 connection considerations). Landed in sub-phases:
package spike and local wildcard-subdomain dev environment; the
landlord/tenant migration split; stripping `school_id`/teams and
splitting Super Admin into a separate `PlatformUser`/guard; provisioning
and the cross-domain signup handoff; the 249-test suite's migration to
per-test tenant databases (SQLite, real temp files, not `:memory:`); and
this phase's own frontend wiring plus a live browser pass, which caught
several bugs invisible to `php artisan test`/`npm run build` alone
(a CORS regex silently discarded by an unquoted `.env` value chief among
them — see deployment.md § 4).

## What's next (after Phase 14)

Every phase on the original roadmap — SaaS platform layer, the full
domain feature set (Phase 7-12), hardening (Phase 13), and the
database-per-tenant conversion (Phase 14) — is done. Further work from
here is user-directed rather than roadmap-driven: real pricing decisions,
onboarding real schools, or whatever comes up once this is actually in
front of users.

## Conventions a new module should follow

These aren't aspirational — they're the actual pattern every module so far
was built to, verified by 164 passing backend tests and a working frontend.
Deviating from them means the new module won't feel like part of the same
system.

### Backend, per entity

1. **Migration** — `school_id` foreign key (unless deliberately global),
   `BelongsToSchool`-compatible, soft-deletes if the row needs an audit
   trail or "undo," indexes on whatever the row will be filtered/joined by.
2. **Model** — `BelongsToSchool` trait (see the `User` exception in
   [architecture.md](architecture.md#multi-tenancy) before assuming it
   always applies), `LogsActivity` if the model is sensitive enough to
   audit, factory for tests/seeders.
3. **Policy** — extend `BaseModulePolicy`, add `belongsToActorsSchool()`
   checks, add the model's permissions to `config/permissions.php`. If a
   method needs a narrower type than the base class's `Model $model`
   (e.g. to read a model-specific column), **don't narrow the parameter
   type** — PHP treats that as a fatal class-declaration error the moment
   the policy is loaded, not just when the method runs, which breaks every
   request touching that policy at all, including unrelated `index` calls.
   Keep the `Model $model` signature and rely on the caller only ever
   passing the right concrete type (Laravel's policy resolution guarantees
   this). This exact bug shipped in `SectionPolicy` and went undetected for
   multiple phases because no test exercised Section endpoints over HTTP —
   see `tests/Feature/Academic/SectionTest.php`'s regression test.
4. **Any `date`-cast column** — filter and compare it with `whereDate()`,
   never a plain `where('column', $string)` or `whereBetween`. Eloquent's
   `date` cast writes a full `Y-m-d H:i:s` value on SQLite (dev/test) but a
   clean `Y-m-d` on MySQL (production) for the exact same code path — see
   [database.md](database.md#key-modeling-decisions). This bit four call
   sites in the attendance module before being fixed uniformly; any phase
   with its own date columns (exam dates, invoice due dates, leave dates)
   will hit the same trap if this isn't followed from the start.
5. **Form Requests** — one Store + one Update (or a single shared one if
   the rules are identical), authorization delegated to the Policy.
6. **API Resource** — shape the JSON, include relationships via
   `whenLoaded()` not eager-always, remember the `UserResource.permissions`
   lesson: don't gate a field's inclusion by route name if any consumer
   caches the response without a guaranteed follow-up fetch.
7. **Controller** — thin; extend `CrudController` if the entity really is
   plain CRUD, write a dedicated controller if it has real behavior
   (compare `DepartmentController` vs. `StudentController`).
8. **Service** — only if there's logic beyond "validate and save" (ID
   generation, state-transition rules, cross-model aggregation). Don't
   create a Service that's just a pass-through to `Model::create()`.
9. **Tests** — happy path, permission-denied, cross-school isolation, and
   any row-level-scoping behavior specific to the entity. Add to
   `tests/Feature/<Module>/`.

### Frontend, per module

1. **`src/api/endpoints/<module>.ts`** — use `createCrudEndpoints<T,P>`
   (`src/api/endpoints/crudFactory.ts`) for plain CRUD; hand-write for
   anything with non-CRUD actions (compare `academics.ts` vs `students.ts`).
2. **`src/types/<module>.ts`** — mirror the backend Resource shape exactly;
   this is the contract, keep it in sync when the Resource changes.
3. **`src/features/<module>/schemas/`** — zod schema shared between
   live form validation and submit.
4. **`src/features/<module>/pages/`** — list page uses `DataTable` +
   `usePagination` + `useCrudResource`; detail/form pages follow the
   existing student/academic pages' layout conventions (breadcrumb, page
   header with actions, tabs if the entity has sub-resources).
5. **Route registration** — `React.lazy()` in `AppRouter.tsx`, wrapped in
   `PermissionRoute` with the matching permission string.
6. **Nav entry** — `src/config/navigation.ts`, gated by the same
   permission string `PermissionRoute` uses, grouped under the appropriate
   sidebar section.
7. **Tests** — component test for anything with real interaction logic
   (not every dumb display component needs one), schema test if the zod
   schema has non-trivial rules.
8. **Manual smoke test before calling it done** — see
   [testing.md § Manual/browser smoke testing](testing.md#manualbrowser-smoke-testing).
   This project's worst bug was invisible to every automated test.

### Cross-cutting decisions worth pausing for

Most module work should proceed without asking — but a few things are
genuinely cross-cutting enough to warrant a check-in when they first come
up, rather than guessing:

- The first real payment-gateway integration choice, once
  `PaymentGatewayInterface` needs a concrete non-manual implementation
  (Phase 8 — school-to-parent fee billing; distinct from Phase 6's
  platform-to-school Stripe subscription billing, which is a separate,
  already-decided integration)
- Anything that would require a schema change to already-shipped Phase 0-3
  tables (rather than purely additive new tables) — these are in
  production use by the time later phases land and deserve a deliberate
  migration/backfill plan, not a silent `ALTER TABLE`.

Everything else — a new CRUD entity, a new role's permission set, a new
dashboard widget — follows the conventions above directly.
