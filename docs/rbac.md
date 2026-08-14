# Roles & Permissions (RBAC)

## Model

`spatie/laravel-permission` v8 with the **teams** feature turned on. In
`config/permission.php`:

```php
'teams' => true,
'team_foreign_key' => 'school_id',
```

This makes every role and permission assignment implicitly scoped to a
`school_id`. A user's roles in School A are invisible when the "current
team" is School B — the package handles this natively via a `school_id`
column on its pivot tables, no custom scoping code needed.

**Team context** is set once per request by
`App\Http\Middleware\EnsureSchoolContext` (registered in the `api`
middleware group):

```php
PermissionRegistrar::setPermissionsTeamId($user->school_id ?? 0);
```

**Super Admin** has no `school_id` (`null`), which maps to reserved team id
`0`. This is a sentinel value, not a real row in `schools` — team foreign
keys have no FK constraint, so `0` is safe to use as "no school / all
schools." Super Admin also gets a `Gate::before` closure that short-circuits
every authorization check to `true`, so it doesn't need every permission
individually assigned.

## Permission catalogue

Permissions are named `module.action` and generated from
`config/permissions.php`:

| Module | Actions |
|---|---|
| `schools` | manage |
| `users` | view, create, edit, delete, manage-mfa *(Phase 15 — `manage-mfa` is what lets an admin clear a locked-out user's TOTP enrollment (`POST /users/{id}/mfa/reset`) and grant them a new setup grace period. Deliberately its own action, not folded into `edit`: `UserPolicy::update()` allows a user to edit their own record, but a user resetting their OWN MFA with no second factor at all would defeat the point of it being mandatory — see `UserController::resetMfa()`'s docblock)* |
| `roles` | view, create, edit, delete |
| `settings` | view, edit |
| `audit-logs` | view |
| `data-export` | school *(Phase 15 — the admin bulk "export the whole school's data" flow. Self-service "export my own data" is deliberately ungated, same self-service shape as leave requests/payslips — see `DataExportController`)* |
| `academic-years` | view, create, edit, delete |
| `academic-structure` | view, create, edit, delete *(departments, grade levels, sections, subjects, rooms, class-subject-teacher links)* |
| `timetable` | view, create, edit, delete |
| `students` | view, create, edit, delete, import, export |
| `guardians` | view, create, edit, delete |
| `enrollment` | manage *(promote/transfer/withdraw/graduate/reactivate)* |
| `student-attendance` | view, mark, edit, export |
| `staff-attendance` | view, mark, edit, export |
| `grading` | view, manage *(grading scales — a single "manage" action, not separate create/edit/delete, since scales are infrequently-changed config)* |
| `exams` | view, create, edit, delete, publish *(`publish` is the whole-exam bulk action — `ExamService::publish()` stamps every subject group's `published_at` too, so it and the per-group `exam-marks.publish` below never disagree once it runs)* |
| `exam-marks` | view, enter, edit, export, publish, import *(Phase 16 — `publish` declares one subject's result independent of the whole exam: Admin/Principal always, otherwise only the class teacher of that subject's own section, see `ExamController::assertCanPublishGroup()`. Deliberately a distinct permission from `exams.publish`, not a rename — a Teacher never gets it, only Class Teacher does. `import` is a later Phase-16 addition — a bulk Excel alternative to the manual marks form, `ExamMarksImport` — granted to exactly the roles that already hold `.enter` (School Admin, Teacher, Class Teacher); Principal has `.edit`/`.publish` but never `.enter`/`.import`, consistent with "oversees, doesn't grade")* |
| `questions` | view, create, edit, delete, import *(the online-test question bank; `import` is Phase 16's Excel MCQ importer, `McqQuestionsImport`, granted to the same roles that already hold `questions.create`)* |
| `online-exams` | view, configure *(attaching questions to an exam subject's online test — not gating the act of a Student taking one, which is self-scoped, not permission-gated)* |
| `homework` | view, create, edit, delete, grade *(Phase 7 — row-scoped for Teacher/Class Teacher the same way as exams: must actually teach the subject/section, see `Homework::isTaughtBy()`)* |
| `remarks` | view, create, edit, delete *(Phase 7 — a Teacher/Class Teacher may only write about a student in a section they teach or lead; Parent visibility additionally gated per-remark by `visible_to_guardian`, see [database.md](database.md#key-modeling-decisions))* |
| `dashboard` | view |
| `platform` | view-tenants, manage-billing, view-metrics, offboard-schools *(Super Admin only, cross-tenant — the platform admin console added in Phase 6. Distinct from the school-scoped `billing` module below; see [database.md](database.md#key-modeling-decisions). `offboard-schools` is Phase 15's whole-school anonymize/delete action, `PlatformSchoolController::offboard()`)* |
| `billing` | view, manage *(School Admin only, school-scoped — "our plan/trial", added in Phase 6 Sub-phase E. Distinct from `platform.*`'s cross-tenant scope, and from `fees`/`invoices` below for school-to-parent billing)* |
| `fees` | view, create, edit, delete *(Phase 8 — fee categories and fee structures, the templates invoices are generated from)* |
| `invoices` | view, create, edit, delete, void, record-payment, issue-credit-note, view-reports *(Phase 8 — invoices, payments via `App\Contracts\PaymentGatewayInterface`'s manual implementation, credit notes, and financial reports. Row-scoped for Student/Parent the same way exams/homework are — see below)* |
| `designations` | view, create, edit, delete *(Phase 9 — job-title lookup CRUD, assigned to a user via `PUT /users/{id}`)* |
| `leave` | view, manage *(Phase 9 — `view` sees every staff member's requests; `manage` additionally covers leave-type CRUD and approve/reject. Filing/viewing your *own* leave request is deliberately unpermissioned — same self-service shape as staff-attendance's self check-in — so neither action appears here)* |
| `payroll` | view, manage *(Phase 9 — `view` sees every staff member's payslips/structures; `manage` covers creating salary structures, generating payslips, and marking them paid. Deliberately not granted to Principal/Management by default — salary data is sensitive in a way attendance/leave aren't. Viewing your own payslips is likewise unpermissioned)* |
| `library` | view, manage *(Phase 10 — books and book issues; `manage` covers CRUD on the catalog plus issuing/returning)* |
| `transport` | view, manage *(Phase 10 — vehicles, routes/stops, student transport assignments)* |
| `hostel` | view, manage *(Phase 10 — hostels, hostel rooms, allocations)* |
| `front-desk` | view, manage *(Phase 10 — visitor check-in/check-out log)* |
| `certificates` | view, create, edit, delete, issue *(Phase 11 — template CRUD plus `issue`, bolted on the same way `invoices.void` was. Viewing your own issued certificate is self-scoped for Student/Parent, same shape as `invoices` — they're granted `certificates.view` directly, not exempted)* |
| `notice-board` | view, create, edit, delete, publish *(Phase 11 — `view` here means "see drafts and manage the board" (School Admin/Principal/Management oversight); reading the **published** board needs no permission at all — see `NoticePolicy`)* |
| `communication` | view, manage *(Phase 11 — composing/sending an announcement broadcast. Reading your own notification inbox needs no permission at all, same self-service shape as leave requests/payslips)* |

**Phase 12 deliberately added no new modules here at all.** Its four
report endpoints and its search endpoint aren't new data — they're a
different *view* of data every permission above already gates, so each
one just checks the existing permission for whatever it's reporting on
(`ReportController::attendance()` checks `student-attendance.view`/
`staff-attendance.view` directly, `academicPerformance()` checks
`exam-marks.view`, `enrollment()` checks `students.view`, `operations()`
checks `library.view`/`transport.view`/`hostel.view` — same idea as
`invoices.view-reports` hanging off the `invoices` module back in
Phase 8, generalized to "reuse the domain's own `.view`" since these
reports span module boundaries that no single existing Policy owns). No
Policy class backs any of the five — like `platform.*`, they're
authorized via a direct `$user->can(...)` check in the controller, since
none of them are tied to a single Eloquent model. `SearchService` follows
the exact same idea one level further: the endpoint itself has no gate
at all, and each of its five result categories is independently included
only when the caller holds that category's own permission
(`students.view`, `guardians.view`, `users.view`, `library.view`,
`invoices.view`) — a search for something you can't see just returns
fewer categories, not a 403.

`database/seeders/PermissionSeeder.php` reads this config and creates one
`Permission` row per `module.action` pair. **To add a new module's
permissions:** add the module/actions to `config/permissions.php`, then —
for a school-scoped module (most of them) — add the matching entries to
`App\Services\SchoolProvisioningService::SCHOOL_SCOPED_ROLE_PERMISSIONS`
for whichever roles should have it (this is the single source of truth
both `RolePermissionSeeder` and the self-service signup flow read from,
since Phase 6 extracted it out of the seeder — see
[roadmap.md § Status](roadmap.md#status)); for a cross-tenant,
Super-Admin-only module like `platform`, no matrix entry is needed at
all — Super Admin's role is synced with *every* existing permission
name automatically (`RolePermissionSeeder::allPermissionNames()`), not
a hand-maintained list. Either way, run
`php artisan db:seed --class=PermissionSeeder` (idempotent — uses
`firstOrCreate`) followed by `RolePermissionSeeder`. That reaches schools
being freshly provisioned only — for changes that need to reach
**already-live** tenants, run `php artisan permissions:rollout` (Phase
15) instead, see [deployment.md § 10](deployment.md#10-multi-school-considerations-database-per-tenant).

The frontend's role editor fetches the live catalogue from
`GET /api/v1/permissions` rather than hardcoding it, so custom roles built
in the UI automatically see new modules once seeded.

## Default roles

`App\Services\SchoolProvisioningService` defines a
`SCHOOL_SCOPED_ROLE_PERMISSIONS` matrix for 12 school-scoped roles
(`database/seeders/RolePermissionSeeder.php` just delegates to it per
existing school, and the self-service signup flow calls the same
method for a brand-new one), plus Super Admin (team `0`, granted every
permission unconditionally):

| Role | Scope |
|---|---|
| **School Admin** | Full access to every module within their school, including attendance, grading, exams, exam-marks, questions, online-exams, and homework/remarks, plus `billing.*` (view/manage the school's own plan, usage, and Stripe Customer Portal — Phase 6 Sub-phase E), full `fees.*`/`invoices.*` (Phase 8), full `designations.*`/`leave.*`/`payroll.*` (Phase 9), full `library.*`/`transport.*`/`hostel.*`/`front-desk.*` (Phase 10), and full `certificates.*`/`notice-board.*`/`communication.*` (Phase 11) |
| **Principal** | View-heavy across all modules, manage enrollment/academic-structure, view+correct+export both attendance modules and exam-marks, full `exams.*`/`grading.*` (no `exam-marks.enter` — Principal doesn't grade, only oversees/corrects); `homework.view`/`remarks.view` only, no `homework.grade`; `fees.view` + `invoices.view`/`invoices.view-reports` (oversight, no create/edit/record-payment); `designations.view` + `leave.view` (oversight) but **no `payroll.*` at all** — salary data stays HR/Admin-only; `library.view`/`transport.view`/`hostel.view`/`front-desk.view` (oversight, no `manage` — Phase 10 mirrors the same read-only pattern); `certificates.view`/`notice-board.view`/`communication.view` (Phase 11, same oversight-only pattern — sees draft notices and sent-announcement history, can't issue a certificate or compose one) |
| **Management** | View-heavy, reporting-oriented (mirrors Principal minus edit/create rights; same `fees.view`/`invoices.view`/`invoices.view-reports`/`designations.view`/`leave.view`/`library.view`/`transport.view`/`hostel.view`/`front-desk.view`/`certificates.view`/`notice-board.view`/`communication.view` read access, same payroll exclusion; diverges further once Phase 12 reports land) |
| **Accountant** | Full `fees.*`/`invoices.*` (Phase 8 — this is the primary role for the module: fee structures, invoices, recording payments, credit notes, financial reports), plus view students/guardians/academic-years/academic-structure (needed to browse grade levels and sections when generating invoices) |
| **HR Staff** | Full `staff-attendance.*` (their attendance is a staff-management concern), no `student-attendance.*`/exam modules; full `designations.*`/`leave.*`/`payroll.*` (Phase 9 — this is the primary role for the module, same relationship Accountant has to `fees`/`invoices`); full `hostel.*` (Phase 10 — boarding is an HR/welfare concern at most schools) plus `students.view` (needed to look up a student when allocating a hostel room — see roadmap.md's Phase 10 entry for the bug this was missing at first) |
| **Receptionist** | View students/guardians, create students/guardians (front-desk intake), full `front-desk.*` (Phase 10 — this is the primary role for the visitor log) |
| **Teacher** | View only their assigned sections' students (enforced at the query level, not just permission-gate — see "Row-level scoping" below); `student-attendance.view/mark/edit`, `exam-marks.view/enter/edit/import`, and `homework.create/edit/delete/grade` all restricted to sections/subjects they actually teach; `questions.view/create/edit` (author their own bank) and `online-exams.configure`, both still subject-ownership-checked; full `remarks.*` for students in a section they teach; no `staff-attendance.*` beyond self-check-in |
| **Class Teacher** | Teacher permissions + `student-attendance.export`/`exam-marks.export`/`exam-marks.publish` for their one section |
| **Student** | View only their own profile/records; `student-attendance.view`, `exams.view`, `exam-marks.view`, `homework.view`, `remarks.view`, `invoices.view` for their own records only — the exam *list* is additionally gated by `Exam.is_published` (see below), but a report card's per-subject marks are masked by that subject's own `ExamSubjectGroup.published_at` since Phase 16, not the whole exam (see below); homework submission itself is self-scoped, not permission-gated, same rationale as online-exam taking |
| **Parent/Guardian** | View only their linked children's records, via the Parent Portal endpoints; same view-only exam/attendance/homework/invoices access as Student, scoped to linked children; remarks additionally filtered to `visible_to_guardian = true` |
| **Librarian** | Full `library.*` (Phase 10 — this is the primary role for the module: catalog CRUD, issuing/returning books) plus `students.view` (needed to look up a student when issuing a book) |
| **Transport Staff** | Full `transport.*` (Phase 10 — vehicles, routes/stops, student assignments) plus `students.view` (needed to look up a student when assigning transport) |

Every school-scoped role (and Super Admin) can self check-in/check-out via
`staff-attendance`'s `selfCheckInOut` policy ability regardless of whether
they hold `staff-attendance.mark` — see the [Attendance section of
api.md](api.md#attendance) and `StaffAttendancePolicy`'s class doc for why
self-visibility/self-check-in is deliberately not gated behind the blanket
permission.

Roles for modules that don't exist yet (Librarian, Transport Staff, and
Accountant/HR/Receptionist's fuller scope) are seeded now so they're stable,
referenceable identities — permissions get added to them additively as each
phase lands, without ever needing to rename or recreate the role.

**Custom roles:** School Admins can create additional roles via
`POST /api/v1/roles` scoped to their own school (Super Admin can create
global ones). The Roles UI (`RoleFormModal`) renders the full permission
catalogue as a checkbox matrix.

## Row-level scoping ("Own" vs "All")

Permission checks answer *"can this user do X at all,"* not *"which rows."*
Row-level restriction (a Teacher seeing only their section's students, a
Parent seeing only their linked children) is enforced in the query layer,
not the permission layer:

- `Student::scopeVisibleTo($query, $user)` — a teacher can see students
  whose `current_section_id` matches a section where `class_teacher_id`
  is theirs; a parent-linked user is restricted via the `Guardian` pivot.
- `App\Services\DashboardService` switches its aggregation query per role
  (`role_context: 'staff' | 'teacher' | 'parent'`) rather than exposing one
  query with a permission-gated `WHERE`.
- Parent Portal endpoints (`/api/v1/parent/*`) don't take a student ID from
  an arbitrary route param trusted at face value — they resolve "children"
  from the authenticated user's own `Guardian` link first.
- `StudentAttendance::scopeVisibleTo($query, $user)` mirrors `Student`'s
  scope exactly (same Teacher/Class Teacher/Student/Parent rules), applied
  to the attendance rows themselves rather than the students they're about.
- `StudentAttendanceController::assertSectionMarkable()` is the same idea
  applied to a *write*, not a query: `student-attendance.mark` alone would
  let any Teacher mark attendance for *any* section, since the permission
  itself carries no row context. The controller separately checks the
  target section against `class_teacher_id`/`ClassSubjectTeacher` before
  allowing a Teacher/Class Teacher to proceed — School Admin/Principal/Super
  Admin, who hold blanket authority, skip this check.
- `StaffAttendancePolicy` takes the opposite shape from the others here: its
  `viewAny` is unconditionally `true` and self-records are always visible —
  the *permission* (`staff-attendance.view`) only expands visibility to
  *other* staff's records, rather than gating the endpoint outright. See its
  class docblock.
- `Exam::scopeVisibleTo($query, $user)` adds a dimension the others don't
  need: a *publish* gate. Student/Parent only ever see `is_published: true`
  exams touching their own/their child's section; Teacher/Class Teacher see
  exams touching a section they teach regardless of publish state (they
  need to see drafts to enter marks against them). Without this scope,
  `GET /exams`'s blanket `exams.view` permission would leak every exam in
  the school — including unpublished ones — to every Student; this was a
  real gap caught during Phase 5 development, not a hypothetical.
- `ExamMark::scopeVisibleTo()` mirrors it: Student/Parent additionally
  require the parent Exam to be published; Teacher/Class Teacher don't
  (same reasoning — they produce the marks that get published later).
- **Phase 16 nuance**: the report card endpoints (`ExamController::
  reportCard`/`reportCardPdf`, and the parent-portal equivalents) do NOT
  use `ExamMark::scopeVisibleTo()` at all — they call
  `ExamService::reportCard($exam, $student, $viewer)`, which masks each
  subject *independently*: visible if `Exam.is_published` OR that
  specific `ExamSubjectGroup.published_at` is set (see
  `ExamSubjectGroup::status()`). This is intentionally a strict superset
  of the old whole-exam-only rule — a Class Teacher's early per-subject
  publish (`exam-marks.publish`) reaches the student immediately, without
  waiting for the whole exam. The endpoint itself no longer 403s a
  Student/Parent on an unpublished exam; it returns 200 with every
  not-yet-declared subject's marks/percentage/grade nulled out and only
  `group.status` (`draft`/`calculated`/`published`) exposed.
- **Later Phase-16 addition, same rule extended to Online MCQ**:
  `OnlineTestAttemptResource` (backing `submit()`/`show()`) and
  `OnlineTestController::myTests()` independently apply the identical
  `ExamSubjectGroup::status()` check to `score`/`max_score`/`answers` —
  auto-grading still happens and writes `ExamMark` the instant a student
  submits, but the student's own view of that score is masked exactly like
  every other component type until the subject is declared. A staff viewer
  with `exam-marks.view` (excluding Student/Parent) always sees it. This
  closed a real gap: before this, an Online MCQ result bypassed
  Draft/Calculated/Published entirely and was visible to the student the
  instant they hit submit, regardless of anyone declaring anything.
- `ExamMarkController::assertSubjectMarkable()` / `OnlineTestController::assertCanConfigure()`
  are the write-side equivalent of `assertSectionMarkable()` above, scoped
  to `ExamSubject::isTaughtBy()` (a `ClassSubjectTeacher` match) rather than
  `Section::class_teacher_id` — entering marks or configuring an online test
  is a subject-level privilege, not a whole-section one, so even a Class
  Teacher must be the assigned subject teacher to grade it (they can still
  *view* every subject in their section, just not grade ones they don't
  teach).
- `Invoice::scopeVisibleTo(Builder $query, User $user)` / `Payment::scopeVisibleTo()`
  (Phase 8) mirror Homework's Student/Parent branches exactly (own record /
  linked child's record via `Student::guardians()`) but have no Teacher
  branch — Teacher/Class Teacher never hold `invoices.view` at all, so
  there's nothing to narrow for them. Staff roles that do hold it (School
  Admin, Principal, Management, Accountant) are school-wide, no row
  narrowing — the blanket permission is the only gate for them.
- `LeaveRequestController::index()` / `PayslipController::index()` (Phase 9)
  take the exact shape `StaffAttendanceController::index()` established in
  Phase 4: the endpoint itself is unconditionally reachable
  (`viewAny` is permissive), and the query is narrowed to
  `where('user_id', $request->user()->id)` only when the caller lacks
  `leave.view`/`payroll.view` — the permission expands *whose* records are
  visible, it never gates the endpoint outright. `LeaveRequestPolicy::create()`
  is likewise unconditional for any `$user->isStaff()` — filing your own
  leave request needs no permission at all, same rationale as
  `StaffAttendancePolicy::selfCheckInOut()`.
- `Notice::scopeVisibleTo(Builder $query, User $user)` (Phase 11) takes the
  *opposite* direction from every scope above: instead of narrowing to
  "your own records," it narrows to "records visible to the public" —
  `notice-board.view` holders see everything including drafts, everyone
  else is filtered to `is_published = true`, a matching `audience`, and
  not yet `expires_at`. `NoticePolicy::viewAny()`/`view()` are both
  unconditionally `true`, the same "gate is permissive, the scope is the
  real restriction" shape `StaffAttendancePolicy` established — reading
  the notice board needs no permission at all, only *seeing drafts* does.
- `AppNotificationController::index()` (Phase 11) doesn't need a
  `scopeVisibleTo()` at all — it's hardcoded to
  `where('user_id', $request->user()->id)` with no permission-based
  expansion, because there's no legitimate "see everyone's notifications"
  view for anyone, not even School Admin. `AppNotificationPolicy::view()`/
  `update()` both check `$model->user_id === $user->id` directly.

`tests/Feature/Students/StudentAdmissionTest::test_teacher_can_only_see_students_in_their_assigned_sections`,
`tests/Feature/Dashboard/DashboardSummaryTest`,
`tests/Feature/Attendance/{StudentAttendanceTest,StaffAttendanceTest}`, and
`tests/Feature/Exams/{ExamTest,OnlineExamTest,TermResultTest}` are the
regression tests for this; any new row-scoped module should add an
equivalent test rather than trusting the permission check alone.

## Policies

Every model has an `App\Policies\{Model}Policy`. Most extend
`BaseModulePolicy`, which provides:

- `viewAny`/`view`/`create`/`update`/`delete` mapped to the model's
  `module.action` permission strings
- `belongsToActorsSchool(Model $model)` — the tenant-isolation check: even
  if a user somehow has the right permission, the policy still denies
  access to a row from a *different* school. This is what caught the
  original `RolePolicy` bug (permission check alone wasn't enough — a
  School Admin in School A could otherwise edit a role in School B by ID).

**`App\Policies\BaseViewManagePolicy`** (Phase 10) is a second, narrower
base for modules whose permission catalogue is just `view`/`manage` — no
separate create/edit/delete verbs. `viewAny`/`view` map to `.view`;
`create`/`update`/`delete` all map to the same `.manage` permission. Ten
Phase 10 policies (`Book`, `BookIssue`, `Vehicle`, `Route`, `RouteStop`,
`StudentTransportAssignment`, `Hostel`, `HostelRoom`, `HostelAllocation`,
`Visitor`) reduce to a one-line subclass declaring
`protected string $permissionPrefix`, generalizing the same shape
`GradingScalePolicy` had already used by hand since Phase 4-5.

**When adding a new model:** create its Policy extending
`BaseModulePolicy`, register it in `AuthServiceProvider`/`AppServiceProvider`
(Laravel 13 auto-discovers `{Model}Policy` by convention, so this is often
automatic), and add a `tests/Feature/Authorization/*` case asserting
cross-school denial — don't assume the base class covers a model with
non-standard ownership (e.g. `User`, which isn't scoped the same way as
other models — see [architecture.md § Multi-tenancy](architecture.md#multi-tenancy)).

## Frontend permission checks

`usePermission()` (`src/hooks/usePermission.ts`) reads `permissions` off
the cached `AuthContext` user and exposes:

```ts
const { can, hasRole } = usePermission()
can('students.create')   // → boolean
hasRole('Teacher')
```

- **`PermissionRoute`** wraps a route and redirects to `Forbidden` if the
  check fails — this is defense in depth, not the actual security boundary
  (the API enforces the real check via Policies regardless of what the SPA
  renders).
- **`RoleBasedNav`** filters sidebar sections by `can(...)`, so a Teacher
  never sees a "Roles & Permissions" link to begin with.

**Critical gotcha already hit once:** `UserResource`'s `permissions` field
must be included unconditionally, not gated by route name. `AuthContext`
caches whatever shape the *login* response returns without a follow-up
`/me` fetch — if `permissions` is missing from that specific response, every
`can()` check silently fails and the entire nav collapses to just
"Dashboard," with no error thrown anywhere. This was caught only by manual
browser testing, not by any automated test, because no Vitest test
exercised the full login → nav-render path against a real backend
response shape. If you touch `UserResource`, keep `permissions` unconditional.
