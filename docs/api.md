# API Reference

Base URL: `{APP_URL}/api/v1`. All responses use the envelope described in
[architecture.md § Response envelope](architecture.md#response-envelope).
All routes below except `auth/login`, `auth/signup`, `auth/forgot-password`,
`auth/reset-password`, `settings/public`, and `plans` require an
authenticated Sanctum session (see
[architecture.md § Authentication](architecture.md#authentication)).
`POST /stripe/webhook` and `GET /stripe/payment/{id}` are also
unauthenticated but live outside `/api/v1` entirely (registered in
`routes/web.php`, not `api.php`) since Stripe calls them server-to-server
— see [roadmap.md § Status](roadmap.md#status) for why.

Conventions used throughout:
- **List endpoints** support pagination (`?page=`, `?per_page=`), filtering
  and sorting via `spatie/laravel-query-builder` (`?filter[status]=active`,
  `?sort=-created_at`), and return `meta.pagination`.
- Authorization on every route is enforced by a Policy — the tables below
  note the permission each action maps to (see [rbac.md](rbac.md)), but the
  actual denial is a 403 from the Policy, not a route-level check.
- Row-level scoping (a Teacher only seeing their section, a Parent only
  their children) applies transparently on top of the permission check —
  see [rbac.md § Row-level scoping](rbac.md#row-level-scoping-own-vs-all).
- Every authenticated route also passes through `EnsureSchoolIsUsable`
  (Phase 6 Sub-phase E), which can reject a request before it even
  reaches the Policy: HTTP 402 for a school whose plan-limit was hit
  (`PlanLimitExceededException`) or whose billing is `past_due` (writes
  only — reads still work) or fully locked
  (`canceled`/`unpaid`/`incomplete_expired`); `/auth/me`, `/auth/logout`,
  and both `/billing` routes stay reachable even from a locked school.
  See [database.md § Key modeling decisions](database.md#key-modeling-decisions).

## Auth

| Method | Path | Notes |
|---|---|---|
| POST | `/auth/login` | `{ email_or_username, password, remember? }`. Throttled 10/min. |
| POST | `/auth/signup` | No auth. Self-service tenant signup — `{ school: {...}, admin: {...}, plan_id }`. Provisions the school+admin+roles, logs the admin in immediately, returns `{ user, checkout_url }` for the frontend to redirect to Stripe Checkout. Throttled 5/hour. 502 (not 422/500) if Stripe Checkout creation itself fails, after compensating by soft-deleting the just-created school+admin. |
| POST | `/auth/logout` | Ends the session. |
| GET | `/auth/me` | Current user (`UserResource`, includes `permissions` and, when `school.plan` is eager-loaded, a `school` billing snapshot). |
| PUT | `/auth/password` | Change own password (`current_password`, `password`). |
| POST | `/auth/forgot-password` | Throttled 5/min. |
| POST | `/auth/reset-password` | Throttled 5/min. |
| POST | `/auth/email/verification-notification` | Resend verification email. Throttled 6/min. |
| GET | `/auth/email/verify/{id}/{hash}` | Signed URL, verifies email. |

## Two-factor authentication (Phase 15)

Mandatory for every account — see [mfa.md](mfa.md) for the full picture
(enforcement, grace period, recovery). `platform-mfa/*` are the identical
central-zone twins for the `platform` guard/`PlatformUser`.

| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | `/auth/login` | None | Now returns `{ mfa_required: true, challenge_token }` instead of the user, if the account already has MFA confirmed — no session is established yet. |
| POST | `/auth/mfa/verify-challenge` | None (guest) | `{ challenge_token, code }` — `code` may be a TOTP code or a recovery code. Completes the actual login, returns `UserResource` exactly like a non-MFA `/auth/login` would. Throttled 10/min. |
| POST | `/auth/mfa/setup` | Session | Generates a fresh unconfirmed secret, returns `{ secret, qr_code }` (a base64 PNG data URI). Calling again before confirming discards the pending secret. |
| POST | `/auth/mfa/confirm` | Session | `{ code }` — verifies against the pending secret, confirms MFA, returns `{ recovery_codes: [...] }` **once**. |
| POST | `/auth/mfa/recovery-codes/regenerate` | Session | `{ password }` — password-confirmed. Invalidates the old recovery codes, returns a fresh set. |
| POST | `/auth/platform-login` | None | Platform-guard twin of `/auth/login`. |
| POST | `/auth/platform-mfa/verify-challenge` | None (guest) | Platform-guard twin. |
| POST | `/auth/platform-mfa/setup` | Session | Platform-guard twin. |
| POST | `/auth/platform-mfa/confirm` | Session | Platform-guard twin. |
| POST | `/auth/platform-mfa/recovery-codes/regenerate` | Session | Platform-guard twin. |

## Public

| Method | Path | Notes |
|---|---|---|
| GET | `/settings/public` | No auth. Returns `is_public=true` settings (global defaults merged with the caller's school override, if resolvable from context) — powers pre-login branding. |
| GET | `/plans` | No auth. Active subscription plans, sorted by `sort_order` — the signup wizard's pricing step. `PlanResource` deliberately omits `stripe_product_id`/`stripe_price_id`. |

## Dashboard

| Method | Path | Notes |
|---|---|---|
| GET | `/dashboard/summary` | Role-aware. Returns `role_context: 'staff'\|'teacher'\|'parent'` plus counts relevant to that context (see `DashboardService`). |

## Schools *(Super Admin only)*

| Method | Path | Permission |
|---|---|---|
| GET/POST | `/schools` | `schools.manage` |
| GET/PUT/DELETE | `/schools/{school}` | `schools.manage` |

## Platform admin *(Super Admin only, cross-tenant)*

Distinct from the "Schools" section above: these list/manage *every*
school on the platform (billing, plan, usage), not a single school's
own profile. Authorized directly via the `platform.*` permission
strings (Gate abilities Spatie auto-registers per permission name), not
a Policy — see [rbac.md](rbac.md#permission-catalogue).

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/platform/schools` | `platform.view-tenants` | Paginated, filterable by `billing_status`/`plan_id`. |
| GET | `/platform/schools/{school}` | `platform.view-tenants` | Includes usage vs. plan limits (`School::studentCount()`/`staffCount()`), Stripe customer id. |
| POST | `/platform/schools/{school}/plan` | `platform.manage-billing` | `{ plan_id }` — calls `SubscriptionService::swapPlan()`, the same method a future School-Admin-facing "change plan" action will also use. |
| POST | `/platform/schools/{school}/offboard` | `platform.offboard-schools` | `{ mode: 'anonymize'\|'delete' }` (Phase 15). `anonymize` scrubs PII on every account in that tenant (`AnonymizationService`, reused from the per-user workflow below) and deactivates the school, but keeps the physical database — financial/academic records stay queryable. `delete` physically drops the tenant database (`SchoolProvisioningService::teardown()`, the same sequence a failed signup's rollback already uses) — irreversible. |
| GET | `/platform/metrics` | `platform.view-metrics` | Counts by `billing_status` + an approximated MRR (face-value plan price sum, ignores proration/discounts). |

## Billing *(school-scoped, School Admin only)*

Distinct from the "Platform admin" section above: this is a school's own
view of *its* subscription, not a cross-tenant list.

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/billing` | `billing.view` | Own school's plan, billing status, trial, and usage vs. limits — reuses `PlatformSchoolResource`, the identical shape Sub-phase D's platform console sees for this same school. |
| GET | `/billing/portal` | `billing.manage` | Returns `{ url }` — a Cashier-hosted Stripe Customer Portal session for updating the card or viewing invoice history. Returns a clean 422 (not Cashier's own exception) if `stripe_id` is still null, e.g. mid-signup before Checkout completes. |

## Users & access control

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/users` | `users.view` | Filterable/sortable, scoped to caller's school. |
| POST | `/users` | `users.create` | |
| GET | `/users/{user}` | `users.view` | |
| PUT | `/users/{user}` | `users.edit` | |
| DELETE | `/users/{user}` | `users.delete` | Soft delete. |
| POST | `/users/{user}/roles` | `users.edit` | Replaces the user's role set (school-scoped). |
| POST | `/users/{user}/status` | `users.edit` | Activate/suspend/etc. |
| POST | `/users/{user}/reset-password` | `users.edit` | Admin-triggered reset. |
| POST | `/users/{user}/mfa/reset` | `users.manage-mfa` | Clears the user's TOTP enrollment entirely and grants a new 3-day setup grace period — see [mfa.md](mfa.md#lost-device--recovery). Deliberately its own permission, not `users.edit` (which also allows self-service — resetting your own MFA with no second factor would defeat the point). |
| GET/POST | `/roles` | `roles.view` / `roles.create` | |
| GET/PUT/DELETE | `/roles/{role}` | `roles.view`/`edit`/`delete` | School-scoped; Super Admin can also manage global roles. |
| GET | `/permissions` | `roles.view` | Full permission catalogue, for the role editor's matrix. |

## Settings & audit

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/settings` | `settings.view` | All settings visible to caller (global + school). |
| PUT | `/settings` | `settings.edit` | Bulk upsert `{ settings: [{key, value}, ...] }`. |
| GET | `/audit-logs` | `audit-logs.view` | Filterable by causer/subject/date. |

## Data export (Phase 15)

Both flows share `App\Jobs\GenerateDataExportJob` — the first real queued
job in this app (`QUEUE_CONNECTION=database`, see
[deployment.md](deployment.md) §5) — and produce a ZIP of CSVs, one per
resource. Self-service reuses the same `scopeVisibleTo()` scopes the UI
already uses (`Student`/`Guardian`/`Invoice`/`Payment`), zero new
authorization logic.

| Method | Path | Permission | Notes |
|---|---|---|---|
| POST | `/account/data-export` | None (self-service) | Starts generating the caller's own visible data (account/student-or-guardian profile/invoices/payments). |
| GET | `/account/data-export` | None (self-service) | Lists the caller's own past requests. |
| POST | `/data-exports` | `data-export.school` | Starts generating every record in the tenant (users/students/guardians/invoices/payments). |
| GET | `/data-exports` | `data-export.school` | Lists every school-wide export request. |
| GET | `/data-exports/{export}/download` | Owner (self scope) or `data-export.school` (school scope) | Streams the ZIP. 409 if not yet `ready`, 410 if the file has since been cleaned up (a `retention.data_export_days` setting controls how long, default 7). |

## Account deletion & anonymization (Phase 15)

| Method | Path | Notes |
|---|---|---|
| DELETE | `/account` | Self-service — no permission gate, same self-service shape as `PUT /auth/password`. Anonymizes the caller's own PII (`AnonymizationService`) and soft-deletes their account immediately, then logs them out. No confirmation workflow beyond the request itself in v1. |
| DELETE | `/users/{user}` | `users.delete` (existing, unchanged permission) — now anonymizes before soft-deleting, not just a bare soft-delete. Restoring a soft-deleted user brings back an anonymized shell, not their original data — see [rbac.md](rbac.md). |

`AnonymizationService::anonymizeUser()` draws the PII line per model — see
its own docblock for exactly what's scrubbed vs. retained (a `Student`'s
academic identity/records survive even when their login account doesn't;
`Invoice`/`Payment`/`CreditNote`/`Payslip` are never touched at all,
financial/legal record).

## Academic structure

| Method | Path | Permission |
|---|---|---|
| GET/POST/PUT/DELETE | `/academic-years[/{id}]` | `academic-years.*` |
| POST | `/academic-years/{academicYear}/activate` | `academic-years.edit` — marks `is_current`, deactivates the previous current year |
| GET/POST/PUT/DELETE | `/terms[/{id}]` | `academic-structure.*` |
| GET/POST/PUT/DELETE | `/departments[/{id}]` | `academic-structure.*` |
| GET/POST/PUT/DELETE | `/grade-levels[/{id}]` | `academic-structure.*` |
| GET/POST/PUT/DELETE | `/sections[/{id}]` | `academic-structure.*` |
| GET/POST/PUT/DELETE | `/subjects[/{id}]` | `academic-structure.*` |
| GET/POST/PUT/DELETE | `/rooms[/{id}]` | `academic-structure.*` |
| GET/POST/PUT/DELETE | `/holidays[/{id}]` | `academic-structure.*` |
| GET/POST | `/class-subject-teachers` | `academic-structure.view`/`create` — assigns a teacher to a subject within a section |
| DELETE | `/class-subject-teachers/{id}` | `academic-structure.delete` |
| GET/POST/PUT/DELETE | `/timetable-periods[/{id}]` | `timetable.*` — the reusable time-slot definitions (Period 1: 08:00-08:45, ...) |
| GET | `/timetable` | `timetable.view` | Grid view: entries for a section/teacher/week. |
| POST/PUT/DELETE | `/timetable-entries[/{id}]` | `timetable.create`/`edit`/`delete` — rejects teacher double-booking (see [database.md](database.md#key-modeling-decisions)) |

## Students

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/students` | `students.view` | Row-scoped (Teacher → own sections, Class Teacher → own section). |
| POST | `/students` | `students.create` | Admission — creates the student, generates `admission_number`, optionally creates guardians inline, writes an `admission` enrollment-history row. |
| GET | `/students/{student}` | `students.view` | |
| PUT | `/students/{student}` | `students.edit` | |
| DELETE | `/students/{student}` | `students.delete` | Soft delete. |
| GET | `/students/import/template` | `students.import` | Downloads a blank Excel template with the expected columns. |
| POST | `/students/import` | `students.import` | Bulk admission via Excel; per-row validation, partial success reporting. |
| GET | `/students/export` | `students.export` | Excel export of the (filtered) student list. |
| POST | `/students/bulk/promote` | `enrollment.manage` | Promotes a set of student IDs to a target grade/section together. |
| GET/POST/DELETE | `/students/{student}/documents[/{media}]` | `students.edit` | Upload/list/delete via medialibrary collections (`photo`, `birth_certificate`, `previous_report_card`, `transfer_certificate`, `other`). |
| POST/PUT/DELETE | `/students/{student}/guardians[/{guardian}]` | `students.edit` | Attach/update/detach a guardian link (relationship type, primary flag, pickup permission). |
| GET | `/students/{student}/enrollment-history` | `students.view` | Full timeline of admission/promotion/transfer/withdrawal/graduation/reactivation. |
| POST | `/students/{student}/promote` | `enrollment.manage` | |
| POST | `/students/{student}/transfer` | `enrollment.manage` | |
| POST | `/students/{student}/withdraw` | `enrollment.manage` | |
| POST | `/students/{student}/graduate` | `enrollment.manage` | |
| POST | `/students/{student}/reactivate` | `enrollment.manage` | |
| POST | `/students/{student}/invite-portal-user` | `students.edit` | Creates a `User` (Student role) and links it via `students.user_id`. |

## Guardians & Parent Portal

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/guardians` | `guardians.view` | |
| GET | `/guardians/{guardian}` | `guardians.view` | |
| POST | `/guardians/{guardian}/invite` | `guardians.edit` | Creates a `User` (Parent role) and links it via `guardians.user_id`. |
| GET | `/parent/children` | *(Parent role, self-scoped)* | Children linked to the authenticated user's `Guardian` record — never trusts a client-supplied guardian ID. |
| GET | `/parent/children/{student}/profile` | *(Parent role, self-scoped)* | 403s if `{student}` isn't actually linked to the caller. |
| GET | `/parent/children/{student}/attendance` | *(Parent role, self-scoped)* | `?from=&to=` (default: current month). Returns `{ summary, records }`. Never gated behind `student-attendance.view` — works even if a School Admin edits the Parent role's permissions (see [rbac.md](rbac.md#frontend-permission-checks)). |
| GET | `/parent/children/{student}/exams` | *(Parent role, self-scoped)* | Published exams touching the child's current section. |
| GET | `/parent/children/{student}/report-card` | *(Parent role, self-scoped)* | `?exam_id=`. Same shape as the staff report-card endpoint below. |
| GET | `/parent/children/{student}/report-card/pdf` | *(Parent role, self-scoped)* | `?exam_id=`. Dedicated parent-portal PDF route (not the staff one) — same rationale as the other parent endpoints: never depends on `exams.view`/`exam-marks.view`. |
| GET | `/parent/children/{student}/term-result` | *(Parent role, self-scoped)* | `?term_id=`. |

## Attendance

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/attendance/students` | `student-attendance.view` | Row-scoped (Teacher/Class Teacher → own sections, Student → self, Parent → own children). Filterable by `section_id`, `student_id`, `date`, `status`, `timetable_period_id`. |
| POST | `/attendance/students` | `student-attendance.mark` | Bulk mark: `{ section_id, date, timetable_period_id?, entries: [{student_id, status, remarks?}] }`. `timetable_period_id: null` = whole-day. Upserts — resubmitting the same section/date/period corrects existing rows instead of duplicating. Teacher/Class Teacher restricted to a section they actually teach (403 otherwise, checked server-side regardless of what the UI shows). |
| PUT | `/attendance/students/{studentAttendance}` | `student-attendance.edit` | Single-record correction: `{ status, remarks? }`. |
| GET | `/attendance/students/summary` | *(via `view` on the target Student)* | `?student_id=&from=&to=` (default: current month). Returns `{ total_marked, present_equivalent, percentage, counts }` — see [database.md](database.md#key-modeling-decisions) for the percentage weighting rule. |
| GET | `/attendance/students/section-summary` | `student-attendance.view` | `?section_id=&date=&timetable_period_id=`. Same shape as above, for a whole section on one day. |
| GET | `/attendance/staff` | *(permissive — see note)* | Every authenticated non-Student/Parent user can call this; scoped to their own records unless they hold `staff-attendance.view`, in which case they see everyone's. Filterable by `user_id`, `date`, `status`. |
| POST | `/attendance/staff` | `staff-attendance.mark` | Bulk mark others' attendance: `{ date, entries: [{user_id, status, remarks?}] }`. |
| POST | `/attendance/staff/check-in` | *(any staff, self only)* | Marks the caller Present with `check_in_time = now()`. Upserts today's row. |
| POST | `/attendance/staff/check-out` | *(any staff, self only)* | Sets `check_out_time` on today's row. 422 if the caller hasn't checked in yet. |
| PUT | `/attendance/staff/{staffAttendance}` | `staff-attendance.edit` | Single-record correction. |
| GET | `/attendance/staff/summary` | *(self, or `staff-attendance.view` for others)* | `?user_id=&from=&to=` (default: current month; `user_id` defaults to caller). |

**A note on `date` filters:** every date-range/exact-date query in this module goes through `whereDate()`, never a plain string comparison — Eloquent's `date` cast writes a full `Y-m-d H:i:s` value on SQLite (only MySQL's native `DATE` column type truncates the time part on write), so `?filter[date]=2026-08-08` against a raw string-equality filter silently matches nothing. This bit four separate call sites once (see `AttendanceService`'s docblock) before being fixed uniformly — don't reintroduce a plain `where('date', $string)` anywhere in this module.

## Grading & Examinations

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/grading-scales[/{id}]` | `grading.view` / `grading.manage` | `{ name, is_default?, grade_bands: [{min_percentage, max_percentage, grade_label, grade_point?, remark?}] }`. Bands are always replaced wholesale on update. |
| GET/POST/PUT/DELETE | `/exams[/{id}]` | `exams.*` | Row-scoped like attendance (see [rbac.md](rbac.md#row-level-scoping-own-vs-all)) — `index`/`show` are overridden from the generic CRUD controller specifically for this. `{ academic_year_id, term_id?, name, weight?, exam_subjects?: [...] }`. `exam_subjects` are upserted by `(subject_id, section_id)`, never wholesale-replaced — see [database.md](database.md#key-modeling-decisions), an unrelated edit to the exam's name must not risk cascading away already-entered marks. |
| DELETE | `/exams/{exam}/exam-subjects/{examSubject}` | `exams.edit` | The explicit, separate way to remove one exam subject (and cascade its marks) — never happens implicitly via a PUT to `/exams/{exam}`. |
| POST | `/exams/{exam}/publish` | `exams.publish` | Sets `is_published = true`, stamps `published_at`. Gates Student/Parent visibility of this exam's results everywhere. |
| POST | `/exams/{exam}/unpublish` | `exams.publish` | |
| GET | `/exams/{exam}/report-card` | *(via `view` on the target Student, + publish gate for Student/Parent)* | `?student_id=`. Per-subject marks/grade/percentage plus `overall_percentage`/`overall_gpa` for this one exam. |
| GET | `/exams/{exam}/report-card/pdf` | Same as above | Same data, rendered via `barryvdh/laravel-dompdf` and streamed as a download. |
| GET | `/exam-subjects/{examSubject}/marks` | `exam-marks.view` | Row-scoped like attendance/exams. |
| POST | `/exam-subjects/{examSubject}/marks` | `exam-marks.enter` | Bulk mark: `{ entries: [{student_id, marks_obtained?, is_absent?, remarks?}] }`. Upserts. Teacher/Class Teacher restricted to a subject they're the assigned `ClassSubjectTeacher` for (403 otherwise) — a *stricter* check than attendance's, since it's subject-level not section-level (a Class Teacher can view every subject in their section but only grade ones they personally teach). |
| PUT | `/exam-marks/{examMark}` | `exam-marks.edit` | Single-record correction. |
| GET | `/terms/{term}/result` | *(via `view` on the target Student)* | `?student_id=`. Weighted average across every published exam in the term the student has marks for, plus section rank — see `TermResultService` in [database.md](database.md#key-modeling-decisions). |

### Question bank & online examinations

Auto-graded MCQ/True-False tests — see [database.md § Exams, grading &
online tests](database.md#exams-grading--online-tests) for how these tables
relate. A "test" isn't its own resource; it's just an `ExamSubject` with
`is_online: true` plus a set of attached questions.

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/questions[/{id}]` | `questions.*` | `{ subject_id?, type: 'mcq'\|'true_false', text, default_marks?, explanation?, options: [{option_text, is_correct}] }`. Exactly one option must be `is_correct`; True/False requires exactly 2 options. Options are replaced wholesale on update (see the "accepted limitation" note in [database.md](database.md#exams-grading--online-tests)). |
| POST | `/exam-subjects/{examSubject}/online-test-questions` | `online-exams.configure` | `{ questions: [{question_id, marks?}] }`. Replaces the test's question set wholesale (safe even with existing attempts — see [database.md](database.md#exams-grading--online-tests)). Teacher/Class Teacher must be the assigned subject teacher, same rule as marks entry. |
| GET | `/online-tests/mine` | *(Student, self-scoped)* | Every online-mode `ExamSubject` for the caller's own section, **regardless of the parent exam's publish state** — a test must be taken to produce the marks an exam gets published with, so this deliberately doesn't route through `Exam::scopeVisibleTo()`. Returns attempt/score summary per test. |
| POST | `/exam-subjects/{examSubject}/attempts` | *(Student, self-scoped)* | Starts (or resumes an in-progress) attempt. 422 if not enrolled in the section, outside the `online_starts_at`/`online_ends_at` window, or `max_attempts` exhausted — resuming an already-started attempt is never blocked by the attempt cap. Returns `{ attempt, duration_minutes, questions }`, questions sanitized (no answer key) via a dedicated resource, shuffled per-student if `shuffle_questions`. |
| PUT | `/online-test-attempts/{attempt}/answers` | *(Student, own attempt only)* | `{ question_id, selected_option_id }`. Autosave, one call per question. 422 once the attempt is submitted. |
| POST | `/online-test-attempts/{attempt}/submit` | *(Student, own attempt only)* | Grades every question (MCQ/True-False are always auto-gradable), writes the score into the exam's official `exam_marks` row (`entered_by` = the student themselves, `remarks: 'Auto-graded via online test.'`), and returns the full answer-by-answer breakdown including the correct answers and any `explanation`. |
| GET | `/online-test-attempts/{attempt}` | Own attempt, or `exam-marks.view` for non-Student/Parent staff | Same shape as the submit response. `exam-marks.view` alone isn't a safe staff check here since Students hold it too (for their own marks) — see `OnlineTestController::authorizeOwnAttempt()`'s docblock. |

## Teacher module: homework & remarks (Phase 7)

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/homework[/{id}]` | `homework.*` | Row-scoped like exams — `index`/`show` overridden for this. `{ academic_year_id, section_id, subject_id, title, description?, due_date, max_score? }`. `teacher_id` is always the creating user, not client-supplied. Teacher/Class Teacher restricted to a subject they're the assigned `ClassSubjectTeacher` for on create/edit/delete (403 otherwise) — School Admin unrestricted. For a Student/Parent caller, each row's `my_submission` is eager-loaded and included inline. |
| POST | `/homework/{homework}/attachments` | `homework.edit` | `multipart/form-data`, `{ file }`. Same ownership check as edit/delete. |
| DELETE | `/homework/{homework}/attachments/{media}` | `homework.edit` | |
| GET | `/homework/{homework}/submissions` | `homework.grade` bypass roles, or the assigned teacher | The teacher's roster view: every student currently in the section, joined with their submission if one exists — not just a list of submissions, so "who hasn't submitted yet" is visible directly. |
| POST | `/homework/{homework}/submit` | *(Student, self-scoped)* | `multipart/form-data`, `{ content?, file? }`. Upserts on `(homework_id, student_id)` — resubmitting before grading replaces the previous content/attachment. 403 if the homework isn't assigned to the caller's current section. |
| PUT | `/homework-submissions/{submission}/grade` | `homework.grade` | `{ score?, feedback? }`. Sets `status = graded`, stamps `graded_at`/`graded_by`. Same subject-ownership check as creating the homework. |
| GET/POST/PUT/DELETE | `/student-remarks[/{id}]` | `remarks.*` | Row-scoped — `filter[student_id]` is how the Student Profile/Parent Portal tabs scope this to one student. `{ student_id, category?, body, visible_to_guardian? }`. Teacher/Class Teacher restricted to a student currently in a section they teach or lead (reuses `Student::scopeVisibleTo()`), checked on create/update/delete. |
| GET | `/parent/children/{student}/homework` | *(Parent role, self-scoped)* | Homework for the child's current section, each row's own submission (if any) included — same rationale as the other parent-portal endpoints: never depends on the staff permission matrix. |
| GET | `/parent/children/{student}/remarks` | *(Parent role, self-scoped)* | Only remarks with `visible_to_guardian = true`. |

## Fees / Billing / Accounting (Phase 8)

School-to-parent fee billing — distinct from the `Billing` section above's
platform-to-school subscription concern. `fees` covers billing config
(categories, structures); `invoices` covers the transactional side
(invoices, payments, credit notes, reports).

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/fee-categories[/{id}]` | `fees.*` | Plain CRUD. `{ name, description?, is_active? }`, unique per school. |
| GET/POST/PUT/DELETE | `/fee-structures[/{id}]` | `fees.*` | Billing templates. `{ academic_year_id, grade_level_id?, fee_category_id, name, amount, frequency, due_day_of_month?, is_active? }`. `grade_level_id` null = applies school-wide. `frequency` is informational (`one_time\|monthly\|quarterly\|term\|annual`) — v1 has no recurring-billing scheduler; each period is billed via a manual "Generate Invoices" action. |
| POST | `/fee-structures/{feeStructure}/generate-invoices` | `invoices.create` | `{ section_id?, issue_date, due_date }`. Bulk-creates one invoice per active student matching the structure's grade (optionally narrowed to one section), applying that student's `StudentFeeAssignment` discount if one exists. Idempotent — a student who already has a non-void invoice carrying an item for this exact structure is skipped, not double-billed. One transaction per student, not one around the whole batch — see `InvoiceService::generateFromStructure()`'s docblock and [testing.md](testing.md#manualbrowser-smoke-testing) for why that matters here specifically. |
| GET/POST/PUT/DELETE | `/student-fee-assignments[/{id}]` | `fees.*` | Per-student discount/scholarship against a fee structure. `{ student_id, fee_structure_id, discount_type: none\|percentage\|fixed, discount_value?, reason? }`. |
| GET/POST/PUT/DELETE | `/invoices[/{id}]` | `invoices.*` | Row-scoped like homework/exams — `index`/`show` apply `Invoice::scopeVisibleTo()` so Student/Parent only ever see their own/their child's invoices. `store`: `{ student_id, academic_year_id, issue_date, due_date, notes?, items: [{ fee_category_id, fee_structure_id?, description, quantity?, unit_amount }] }` — an ad-hoc invoice, always created `issued` (no draft workflow in v1). `update`: `{ due_date?, notes? }` only. `destroy` is blocked (422) once any payment exists — void it instead. |
| POST | `/invoices/{invoice}/void` | `invoices.void` | Blocked (422) if `amount_paid > 0` — an invoice with payments must be credited, not voided, so the payment ledger stays meaningful. |
| POST | `/invoices/{invoice}/payments` | `invoices.record-payment` | `{ amount, method: cash\|cheque\|bank_transfer\|upi\|card\|other, reference_number?, paid_at, notes? }`. Delegates the actual recording to `PaymentGatewayInterface` (only a `manual` implementation ships — see below), then recalculates the invoice's `amount_paid`/`status`. Rejects (422) an amount exceeding the current balance. |
| POST | `/invoices/{invoice}/credit-notes` | `invoices.issue-credit-note` | `{ amount, reason }`. Reduces balance without touching `amount_paid` (a refund/write-off, not a payment). Rejects (422) an amount exceeding the balance. |
| GET | `/students/{student}/fee-statement` | *(via Student's own policy)* | Every invoice for a student with running totals (`total_billed`/`total_paid`/`total_credited`/`total_outstanding`) — backs the Student Profile's "Fees" tab. |
| GET | `/payments` | `invoices.view` | School-wide payments ledger for reconciliation, row-scoped the same way as invoices for a Student/Parent caller (though neither reaches this endpoint from the UI — they read payments via their own invoice). |
| GET | `/payments/{payment}/receipt` | *(via Payment's own policy)* | JSON receipt data. |
| GET | `/payments/{payment}/receipt/pdf` | *(via Payment's own policy)* | Same `Pdf::loadView(...)->download()` pattern as exam report cards. |
| GET | `/fee-reports/collection-summary` | `invoices.view-reports` | `?from_date&to_date` (defaults to the current month). Total collected, by payment method, by fee category. |
| GET | `/fee-reports/outstanding-dues` | `invoices.view-reports` | Total outstanding, overdue count, breakdown by grade level. |
| GET | `/parent/children/{student}/invoices` | *(Parent role, self-scoped)* | Same statement shape as the student fee-statement endpoint — the Parent Portal's "Fees" tab renders identically to the Student Profile's. |

**Payment gateway.** `App\Contracts\PaymentGatewayInterface` is the seam between "an invoice got money against it" and how that money was actually collected. `App\Services\Payments\ManualPaymentGateway` (cash/cheque/bank-transfer/UPI/card recorded by staff after the fact — no external charge) is the only implementation Phase 8 ships, bound via `config/payments.php`. A real online gateway (Stripe, Razorpay, PayU, ...) is a deliberate future integration — per `docs/roadmap.md`'s cross-cutting decisions, the first concrete non-manual implementation is a pause-and-confirm-with-the-user checkpoint, not something to guess at. Swapping one in later is a container-binding change in `AppServiceProvider`, not a rewrite of anything that calls the interface.

## Staff / HR (Phase 9)

Designations are a plain lookup CRUD (`fees`-module-style). Leave and payroll
both split the same way: config (leave types) needs no self-service
permission at all, day-to-day requests/payslips are self-scoped and
unpermissioned for the person they belong to, and `leave`/`payroll`'s
`view`/`manage` permissions only ever expand reach to *other* staff's
records — never gate a person's own.

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/designations[/{id}]` | `designations.*` | Plain CRUD — job titles (Math Teacher, Accountant, ...). `{ name, description?, is_active? }`. Assigned to a user via `PUT /users/{id}`'s `designation_id` field, not a dedicated endpoint. |
| GET/POST/PUT/DELETE | `/leave-types[/{id}]` | *(view: any staff member; create/edit/delete: `leave.manage`)* | Config — `{ name, days_allowed_per_year?, is_paid?, description?, is_active? }`. `viewAny` is deliberately unrestricted to any non-Student/Parent user: picking a leave type is the first step of filing your own leave request, so everyone needs read access to the catalog even though only HR/Admin can edit it — see `LeaveTypePolicy`. |
| GET | `/leave-requests` | *(any staff member; `leave.view` expands to every school)* | Self-scoped unless the caller holds `leave.view` — same shape as `GET /attendance/staff`. `filter[user_id]`/`filter[status]`/`filter[leave_type_id]` supported. |
| POST | `/leave-requests` | *(any staff member, self-scoped)* | `{ leave_type_id, start_date, end_date, reason }`. Always files for the caller — there's no "file on behalf of" in v1. `days` is computed server-side (inclusive date diff). |
| GET | `/leave-requests/{id}` | *(own record, or `leave.view` for another's)* | |
| POST | `/leave-requests/{leaveRequest}/cancel` | *(own record, only while `pending`)* | Withdrawing a request once it's been reviewed isn't allowed — ask HR to reverse the decision instead. |
| POST | `/leave-requests/{leaveRequest}/review` | `leave.manage` | `{ status: approved\|rejected, review_notes? }`. On approval, every day in the range is stamped `on_leave` on the staff member's `staff_attendances` (upserted via `AttendanceService::markOnLeave()`, correcting an already-marked day rather than duplicating it) — see [database.md](database.md#key-modeling-decisions). |
| GET/POST/PUT/DELETE | `/salary-structures[/{id}]` | `payroll.*` | HR/Admin only end to end — unlike leave/payslips, there's no "view your own structure" self-service surface (a staff member sees the *result* via their payslips, not the structure itself). `{ user_id, basic_salary, allowances?, deductions?, effective_from }`. Creating a new structure for a user automatically closes their previous active one (`effective_to` set, `is_active = false`) rather than requiring a separate step. |
| GET | `/payslips` | *(any staff member; `payroll.view` expands to every school)* | Self-scoped unless the caller holds `payroll.view`/`payroll.manage` — same self-visibility shape as leave requests. `filter[user_id]`/`filter[status]`/`filter[year]`/`filter[month]`. |
| POST | `/payslips/generate` | `payroll.manage` | `{ month, year }`. One payslip per active `SalaryStructure` in the school, snapshotting its current amounts. Idempotent — a staff member who already has a payslip for that month/year is skipped. One transaction per staff member, not one around the whole batch — same lesson as Phase 8's bulk invoice generation, see `PayrollService::generateForMonth()`'s docblock. |
| POST | `/payslips/{id}/mark-paid` | `payroll.manage` | Rejects (422) if already `paid`. |
| GET | `/payslips/{payslip}/receipt` | *(own record, or `payroll.view`/`.manage` for another's)* | JSON payslip data. |
| GET | `/payslips/{payslip}/receipt/pdf` | *(same as above)* | Same `Pdf::loadView(...)->download()` pattern as fee receipts/report cards. |

## Library, Transport, Hostel & Front Desk (Phase 10)

Four independent sub-modules, each behind its own `view`/`manage`
permission — no separate create/edit/delete verbs (see
[rbac.md](rbac.md#permission-catalogue)). Plain-CRUD entities
(`Book`, `Vehicle`, `Route`, `Hostel`, `HostelRoom`) use the standard
`GET/POST/PUT/DELETE` shape; the rest are custom action endpoints.

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/books[/{id}]` | `library.*` | `{ title, author?, isbn?, category?, total_copies, is_active? }`. `store` sets `available_copies = total_copies`. `update` shifts `available_copies` by any change to `total_copies`, clamped at 0 — copies currently on loan stay accounted for even if `total_copies` shrinks below that count. |
| GET | `/book-issues` | `library.view` | `filter[book_id]`/`filter[student_id]`/`filter[user_id]`/`filter[status]`. |
| POST | `/books/{book}/issue` | `library.manage` | `{ student_id?, user_id?, due_date }` — exactly one of `student_id`/`user_id` (`required_without` + `prohibits`). Rejects (422) if `available_copies < 1`. |
| POST | `/book-issues/{id}/return` | `library.manage` | Computes `fine_amount` from `library.fine_per_day` (a DB-driven Setting) × days late past `due_date`, increments the book's `available_copies`. |
| GET/POST/PUT/DELETE | `/vehicles[/{id}]` | `transport.*` | `{ registration_number, capacity, driver_name?, driver_phone?, is_active? }`. |
| GET/POST/PUT/DELETE | `/routes[/{id}]` | `transport.*` | `{ name, description?, is_active?, stops?: [{ name, sequence? }] }`. `store` creates the route and its nested stops in one transaction. |
| POST | `/routes/{route}/stops` | `transport.manage` | `{ name, sequence? }`. Appends a stop to an existing route — `sequence` defaults to "one past the current count." |
| DELETE | `/routes/{route}/stops/{stop}` | `transport.manage` | |
| GET | `/student-transport-assignments` | `transport.view` | `filter[student_id]`/`filter[route_id]`/`filter[is_active]`. |
| POST | `/student-transport-assignments` | `transport.manage` | `{ student_id, route_id, route_stop_id, vehicle_id?, effective_from }`. Closes the student's previous active assignment (`is_active = false`) before creating the new one — same "close, don't delete" shape as `SalaryStructure`. |
| GET/POST/PUT/DELETE | `/hostels[/{id}]` | `hostel.*` | `{ name, type: boys\|girls\|mixed, address?, warden_name?, warden_phone?, is_active? }`. |
| GET/POST/PUT/DELETE | `/hostel-rooms[/{id}]` | `hostel.*` | `{ hostel_id, room_number, capacity, is_active? }`. |
| GET | `/hostel-allocations` | `hostel.view` | `filter[student_id]`/`filter[hostel_room_id]`/`filter[status]`. |
| POST | `/hostel-allocations` | `hostel.manage` | `{ student_id, hostel_room_id, bed_number?, allocated_date }`. Rejects (422) if the room is already at capacity (`HostelRoom::occupiedCount() >= capacity`); otherwise closes the student's previous active allocation (`status = vacated`) before creating the new one, all in one transaction. |
| POST | `/hostel-allocations/{id}/vacate` | `hostel.manage` | Rejects (422) if already `vacated`. |
| GET | `/visitors` | `front-desk.view` | `filter[name]`/`filter[date]` (matches `check_in_time`)/`filter[status]` (`checked_in` = `check_out_time IS NULL`). |
| POST | `/visitors` | `front-desk.manage` | `{ name, phone?, purpose, whom_to_meet?, notes? }`. Stamps `check_in_time = now()` and `logged_by` server-side. |
| POST | `/visitors/{id}/check-out` | `front-desk.manage` | Rejects (422) if already checked out. |

## Certificates, ID Cards, Notice Board & Communication (Phase 11)

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/certificate-templates[/{id}]` | `certificates.*` | `{ name, type, body, is_active? }`. `body` supports `{{student_name}}`/`{{admission_number}}`/`{{grade_level}}`/`{{section}}`/`{{school_name}}`/`{{date}}` placeholders. |
| GET | `/certificates` | *(any staff holding `certificates.view`; Student/Parent self-scoped)* | Row-scoped like invoices — `Certificate::scopeVisibleTo()`. `filter[student_id]`/`filter[certificate_template_id]`. |
| GET | `/certificates/{id}` | *(own record, or `certificates.view` for another's)* | |
| POST | `/certificate-templates/{certificateTemplate}/issue` | `certificates.issue` | `{ student_id, issued_date? }`. Renders the template's placeholders against the student and stamps a sequential `certificate_number`. |
| GET | `/certificates/{id}/pdf` | *(same visibility as `GET /certificates/{id}`)* | |
| GET | `/students/{id}/id-card/pdf` | *(via `StudentPolicy::view`)* | Rendered live from the `Student` row every time — nothing is stored. QR code encodes the student's `uuid`. |
| GET | `/users/{id}/id-card/pdf` | *(via `UserPolicy::view` — self or `users.view`)* | Same shape, for staff. |
| GET | `/notices` | *(any authenticated user; `notice-board.view` expands to drafts)* | `Notice::scopeVisibleTo()` — published, non-expired, audience-matching for everyone else. `filter[type]`/`filter[audience]`/`filter[is_published]`. |
| GET | `/notices/{id}` | *(same visibility as the index)* | |
| POST/PUT/DELETE | `/notices[/{notice}]` | `notice-board.create`/`.edit`/`.delete` | `{ title, body, type?: general\|event, audience?: all\|students\|staff\|parents, event_date?, start_time?, end_time?, location?, expires_at? }`. Always created unpublished. |
| POST | `/notices/{notice}/publish` | `notice-board.publish` | Rejects (422) if already published. |
| GET | `/announcements` | `communication.view` | Sent-announcement history, newest first. |
| POST | `/announcements` | `communication.manage` | `{ title, body, audience, channels: (in_app\|email\|sms\|push)[] }`. Resolves recipients by audience within the school, creates one `AppNotification` per recipient, and — per selected channel — sends real mail (`Mail::to()->send()`), or calls `SmsGatewayInterface`/`PushGatewayInterface` (only a log-writing default implementation ships; see [database.md](database.md#key-modeling-decisions)). Synchronous, not queued. |
| GET | `/notifications` | *(any authenticated user, always their own)* | `meta.unread_count` alongside the usual pagination meta. `filter[is_read]`. |
| POST | `/notifications/{id}/read` | *(own record only — 404 for anyone else's)* | |
| POST | `/notifications/read-all` | *(own records only)* | |

## Reports, Analytics & Search (Phase 12)

No standalone `reports`/`search` permission — every action below is
authorized directly (no Policy class) against the existing permission of
the module it reports on or searches, per [rbac.md](rbac.md#permission-catalogue).

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/reports/attendance` | `student-attendance.view` and/or `staff-attendance.view` | `?from_date&to_date` (defaults to the last 6 months). `student`/`staff` keys are each `null` unless the caller holds that specific permission; each present section has `overall_percentage`, a monthly `trend` array, and (student only) a `by_section` breakdown. |
| GET | `/reports/academic-performance` | `exam-marks.view` | The most recent 6 published exams: entries count, average percentage, and pass rate (against each `ExamSubject.passing_marks`, not a `GradeBand`). |
| GET | `/reports/enrollment` | `students.view` | 6-month admissions/withdrawals/graduations trend (from `StudentEnrollmentHistory`) plus active-student counts by grade level. |
| GET | `/reports/operations` | `library.view` and/or `transport.view` and/or `hostel.view` | `library`/`transport`/`hostel` keys each `null` unless the caller holds that specific permission. Library: total books, issued this month, currently overdue. Transport: active vehicle count, students assigned. Hostel: room count, capacity vs. occupied, occupancy %. |
| GET | `/search` | *(none — each result category is independently gated, see below)* | `?q=` (minimum 2 characters, otherwise returns empty results rather than erroring). Searches students/guardians/staff (each by first+last name, matched word-by-word so a full "First Last" query works — see [database.md](database.md#key-modeling-decisions)) plus admission number/phone/email/employee ID where relevant, books (title/ISBN), and invoices (invoice number, row-scoped via `Invoice::scopeVisibleTo()`). Each category is present in the response only if the caller holds that module's `.view` permission (`students.view`, `guardians.view`, `users.view`, `library.view`, `invoices.view`), and capped at 5 results per category. |

## Error responses

Validation failures return HTTP 422 with the standard Laravel
`{ errors: { field: [messages] } }` shape wrapped in the `ApiResponse`
envelope. Authorization failures return 403 with no `errors` key.
Not-found returns 404. All exceptions render as JSON (never an HTML error
page) for any request under `/api/*` — see the exception handling in
`bootstrap/app.php`.
