# Tenant Onboarding Guide — Setting Up a New School

This is the guide for a **School Admin** setting up a brand-new school on the
platform after signup — not a developer doc. It walks through the complete,
dependency-ordered setup flow: what to configure first, what depends on what,
and why. The order below is not arbitrary — it's the same order the app's own
demo-data seeder (`backend/database/seeders/TenantDemoDataSeeder.php`) builds
things in, because most of these screens simply won't have anything to select
in their dropdowns until the step before them exists.

If you skip ahead, the UI will generally stop you gracefully (a "create a
section first" empty-state, a required dropdown with no options) rather than
error — but working through the phases in order gets you to a usable system
in the fewest trips back and forth.

## Before you start: how a school maps to a URL

Every school is a separate tenant with its own database and its own
subdomain: `{your-slug}.localtest.me` in local dev (a real domain pattern in
production — see [deployment.md](deployment.md)). Your slug was set at signup
(from your school's short name) and can't be changed casually since it's part
of how every user's browser finds your school's data. Bookmark
`https://{your-slug}.{domain}` — every login, every page, everything happens
there. The Super Admin's platform console lives on a *different* domain
entirely (no subdomain) and is not something a School Admin has access to.

## Phase 1 — Academic foundations

Nothing else in the system can be created until this phase exists, because
almost every other model points back to one of these.

1. **Academic Year** (`Settings → Academic Years`, or wherever your role's
   sidebar shows it) — e.g. "2025-2026". Mark it **current**. Only one
   academic year should be current at a time; students, sections, exams, and
   fee structures all key off "the current year" by default.
2. **Terms** — split the year into grading periods (e.g. Term 1 / Term 2, or
   three trimesters). Exams and report cards are scoped to a term.
3. **Departments** — optional grouping for subjects (e.g. "Mathematics &
   Science"). Skip if you don't need subject grouping/reporting by
   department.
4. **Grade Levels** — the ladder your school teaches (Kindergarten, Grade 1,
   Grade 2, ...), each with a sequence number that determines promotion
   order.
5. **Rooms** — physical rooms, each with a capacity and type (classroom, lab,
   hall). Needed before sections, since a section is usually assigned a home
   room.
6. **Sections** — the actual classes students get admitted into (e.g. "Grade
   1 - A"), each tied to a grade level, the current academic year, and
   (optionally) a room. **You cannot admit a student until at least one
   section exists.**
7. **Subjects** — what's taught (Mathematics, Science, English, ...),
   optionally tied to a department, with an "is elective" flag.
8. **Timetable Periods** — the daily schedule skeleton (Period 1, Break,
   Period 2, Lunch, ...) with start/end times and an `is_break` flag. Shared
   across all sections; you don't set up periods per-section.
9. **Holidays** (optional) — block out non-teaching days on the calendar.

## Phase 2 — Staff

1. **Invite/create staff users** (`Users`) — first name, last name, email,
   and a role. The role determines what they can do; see the role reference
   table below. A user can hold exactly the roles you assign — there's no
   implicit hierarchy beyond what each role's permission set grants.
2. **Designations** (`HR → Designations`, optional but recommended before
   payroll) — job titles like "Teacher", "Senior Teacher", "Vice Principal".
   Attach one to each staff member along with an employee ID and hire date if
   you plan to use payroll later (Phase 12).
3. Assign a **Class Teacher** to each section (edit the section) — this is
   the staff member primarily responsible for that class, distinct from
   subject teachers.

## Phase 3 — Timetable & subject assignments

1. **Class–Subject–Teacher assignments** — for each section, decide which
   teacher teaches which subject. This is what makes "my classes" show up
   correctly for a Teacher/Class Teacher, and what exam-mark-entry and
   homework screens use to know who's allowed to grade what.
2. **Timetable entries** — place each (section, subject, teacher) into an
   actual (day, period, room) slot. One entry per (section, period, day) —
   the system won't let you double-book a section's same period on the same
   day.

## Phase 4 — Students & Guardians

1. **Admit students** (`Students → New`) — first/last name, gender, date of
   birth, grade level, **section** (must already exist — Phase 1), and
   academic year. Admission automatically generates an admission number and
   records an enrollment history entry.
2. **Add guardians** and link them to students with a relationship (father /
   mother / guardian) and a primary flag. A guardian can optionally be
   "invited" — this creates them a Parent-role login so they can see their
   child's attendance, grades, invoices, and homework through the parent
   portal.

From here on, most phases are independent of each other and can be done in
whatever order matches your school's actual priorities — attendance and fees
are usually the two schools set up next, since they're daily/monthly
operational needs.

## Phase 5 — Attendance

Nothing to configure — attendance is marked directly against sections and
students/staff that already exist (Phases 1-2 and 4). Go to `Take
Attendance`, pick a section and date, mark each student present / absent /
late / half-day / excused / on-leave. Staff attendance is the same idea under
`Staff Attendance`.

## Phase 6 — Grading & Exams

1. **Grading Scale + Grade Bands** (`Grading Scales`) — define percentage
   ranges and their letter grades (A: 90-100, B: 80-89, ...). Set one scale
   as **default** — any exam subject that doesn't specify its own scale falls
   back to this one.
2. **Exams** — name, academic year, term, and a weight (used when averaging
   multiple exams into a final grade).
3. **Exam Subjects** — for each exam, which (subject, section) combinations
   are actually being tested, with max marks, passing marks, and a date.
   This is also what makes an exam "visible" to students/parents in that
   section — an exam with no ExamSubjects for a student's section won't show
   up for them at all.
4. **Enter marks** — once an exam subject exists, marks entry opens up for
   every student in that section. Publish the exam when ready — marks aren't
   visible to students/parents until the exam is marked published.
5. **Question Bank** (optional) — build a reusable pool of MCQ / true-false
   questions per subject, used by the online-exam feature if you use it.

## Phase 7 — Homework

Independent of exams. Create homework against a (section, subject, teacher),
set a due date and max score. Submissions and grading happen per-student
after that.

## Phase 8 — Fees & Billing (school billing to parents, not your own SaaS subscription)

This is the school charging *its own* students, not this platform charging
you — see Phase 15 for your subscription.

1. **Fee Categories** — Tuition, Transport, Library, Examination, etc.
2. **Fee Structures** — attach an amount and frequency (one-time / termly /
   annual) to a category for the current academic year, optionally scoped to
   one grade level.
3. **Student Fee Assignments** (optional) — apply a per-student discount
   (percentage or fixed) against a fee structure, e.g. a sibling discount.
   Set this up **before** generating that student's invoice — discounts
   don't retroactively apply to an invoice that already exists.
4. **Invoices** — generate per-student, pulling in the relevant fee
   structures (and any discount) as line items.
5. **Payments** — record money received against an invoice (cash, cheque,
   bank transfer, UPI, card). The invoice's paid/partially-paid/paid status
   updates automatically.
6. **Credit Notes** — issue a partial credit against an invoice for
   adjustments/refunds without altering the original invoice.

## Phase 9 — Library

**Books** (title, author, ISBN, category, copy count) →  **Issue** a copy to
a student or staff member → **Return** it (fines, if any, are computed from
`library.fine_per_day` in Settings). Available-copy counts adjust
automatically on issue/return.

## Phase 10 — Transport

**Vehicles** (registration, driver, capacity) and **Routes** (with ordered
**Route Stops**) can be set up in either order, but both must exist before
you can create a **Student Transport Assignment** linking a student to a
route, stop, and vehicle. A student can only have one *active* assignment at
a time — assigning a new one automatically closes out the old one rather
than erroring.

## Phase 11 — Hostel

**Hostels** (boys / girls / mixed) → **Hostel Rooms** (with a bed capacity)
→ **Allocations** (assign a student to a room + bed). Allocating past a
room's capacity is rejected — free up a bed (vacate an existing allocation)
first.

## Phase 12 — HR & Payroll

Requires Phase 2's designations to already be attached to staff for the
numbers to mean anything on a payslip.

1. **Salary Structures** — basic salary, allowances, deductions per staff
   member. Only one *active* structure per person — creating a new one
   automatically supersedes the old.
2. **Payslips** — generate for a given month/year; this snapshots the
   currently-active salary structure's numbers (later salary changes don't
   retroactively rewrite old payslips). Mark paid once processed.
3. **Leave Types** (Casual, Sick, Earned, Unpaid, ...) with an annual
   allowance and a paid/unpaid flag.
4. **Leave Requests** — staff apply, an HR/Admin user reviews (approve /
   reject). Approving a leave request also stamps matching staff-attendance
   rows as "on leave" for that date range, so attendance and leave records
   stay consistent — you don't need to separately mark attendance for an
   approved leave.

## Phase 13 — Certificates

**Certificate Templates** — write a reusable body with placeholders
(`{{student_name}}`, `{{admission_number}}`, `{{grade_level}}`,
`{{section}}`, `{{school_name}}`, `{{date}}`). **Issue** one against a
specific student — the placeholders are interpolated and permanently
snapshotted into that certificate's stored content, so editing the template
later never changes certificates already issued.

## Phase 14 — Communication

- **Notice Board** — post general notices or dated events, targeted at
  everyone / students / staff / parents, with an optional expiry date.
- **Announcements** — a broadcast that also creates an in-app notification
  for every matching recipient, and (if `PUSHER_*` env vars are configured —
  see [configuration.md](configuration.md)) delivers live via the
  notification bell without a page refresh. If Pusher isn't configured,
  recipients still get it on their next 60-second poll — nothing breaks,
  delivery just isn't instant.
- **Student Remarks** — a teacher can leave academic/behavioral/general notes
  against a student, optionally visible to that student's guardians.

## Phase 15 — Your own subscription (billing tab)

Separate from Phase 8. Under `Settings → Billing` you can see your current
plan, trial end date, and usage against your plan's student/staff limits, and
change plans. This is the Stripe subscription that determines what this
platform charges *your school* — student/staff seat limits are enforced here
(creating a new staff user past your plan's limit returns a clear "plan limit
reached" error rather than silently succeeding).

## Role reference — who can do what

| Role | Typical use | Broad access |
|---|---|---|
| **School Admin** | The account created at signup | Everything — users, roles, settings, billing, all modules |
| **Principal** | School leadership | Read-heavy across the board, can manage academic structure/timetable/exams, no billing |
| **Management** | Trustees/owners who need visibility, not day-to-day control | Read-only across almost everything |
| **Accountant** | Fees/billing operations | Fee categories/structures, invoices, payments, credit notes |
| **HR Staff** | People operations | Users, designations, leave, payroll, staff attendance |
| **Receptionist** | Front desk | Student/guardian creation, visitor log |
| **Teacher** | Subject teacher | Their own classes: attendance, exam marks, homework, remarks |
| **Class Teacher** | Homeroom teacher | Everything Teacher has, plus attendance export and broader remark access for their section |
| **Librarian** | Library desk | Books and issues only |
| **Transport Staff** | Transport desk | Routes, vehicles, assignments only |
| **Student** | Enrolled student (self-service login) | Read-only: their own timetable, attendance, grades, homework, invoices, certificates |
| **Parent** | Guardian (self-service login, if invited) | Read-only: same as Student, scoped to their linked children |

A user can be assigned more than one role if your school's org chart doesn't
map cleanly onto one of these (e.g. a Vice Principal who also teaches).

## Common gotchas

- **Wrong URL, not a bug.** If API calls fail with what looks like a CORS or
  "network error", you're very likely on the wrong hostname — it must be
  `{your-slug}.{domain}`, never bare `localhost` or the platform's central
  domain. See [architecture.md § Multi-tenancy](architecture.md#multi-tenancy).
- **A dropdown with no options usually means an earlier phase is missing.**
  E.g. the Student creation form's Section dropdown is empty because no
  Section has been created yet (Phase 1) — this isn't a bug, it's the
  dependency order this guide describes.
- **Discounts must exist before the invoice does.** A `StudentFeeAssignment`
  discount only applies to invoices generated *after* it's created.
- **"One active X per student/staff" is enforced by replacing, not
  blocking.** Transport assignments, hostel allocations, and salary
  structures all follow this pattern — creating a new one automatically
  closes out the previous one rather than erroring.
