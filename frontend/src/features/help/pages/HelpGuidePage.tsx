import type { ReactNode } from 'react'
import { AlertTriangle, ArrowRight } from 'lucide-react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent, CardHeader, CardTitle, CardDescription, Tabs, Badge } from '@/components/ui'

function Step({ n, title, children }: { n: number; title: string; children: ReactNode }) {
  return (
    <li className="flex gap-3">
      <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">{n}</span>
      <div className="flex flex-col gap-1">
        <p className="text-sm font-medium">{title}</p>
        <p className="text-sm text-muted-foreground">{children}</p>
      </div>
    </li>
  )
}

function Phase({ title, blocked, children }: { title: string; blocked?: string; children: ReactNode }) {
  return (
    <Card>
      <CardHeader>
        <div className="flex flex-wrap items-center gap-2">
          <CardTitle>{title}</CardTitle>
          {blocked && <Badge variant="warning">Requires: {blocked}</Badge>}
        </div>
      </CardHeader>
      <CardContent>
        <ol className="flex flex-col gap-4">{children}</ol>
      </CardContent>
    </Card>
  )
}

function GettingStartedTab({ slug }: { slug: string }) {
  return (
    <div className="flex flex-col gap-4">
      <Card>
        <CardHeader>
          <CardTitle>Your school's own address</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">
            Everything for your school happens at your own subdomain — you're on it right now:{' '}
            <code className="rounded bg-muted px-1.5 py-0.5 text-foreground">{slug}</code>. Bookmark it. If you ever see login or "network"
            errors, the first thing to check is that you're on the right subdomain, not a bare central address.
          </p>
        </CardContent>
      </Card>

      <Phase title="Phase 1 — Academic foundations">
        <Step n={1} title="Academic Year">
          Create the current year (e.g. "2025-2026") and mark it current. Only one year should be current at a time — students, sections,
          exams, and fees all default to it.
        </Step>
        <Step n={2} title="Terms">Split the year into grading periods. Exams and report cards are scoped to a term.</Step>
        <Step n={3} title="Departments (optional)">Group subjects for reporting, e.g. "Mathematics &amp; Science".</Step>
        <Step n={4} title="Grade Levels">The ladder your school teaches — Kindergarten through your highest grade, each with a sequence number.</Step>
        <Step n={5} title="Rooms">Physical rooms with a capacity and type. Needed before Sections, since a section usually has a home room.</Step>
        <Step n={6} title="Sections">
          The actual classes students get admitted into. <strong>You cannot admit a student until at least one section exists.</strong>
        </Step>
        <Step n={7} title="Subjects">What's taught — Mathematics, Science, English, and so on.</Step>
        <Step n={8} title="Timetable Periods">The daily schedule skeleton (Period 1, Break, Period 2, ...), shared across every section.</Step>
      </Phase>

      <Phase title="Phase 2 — Staff" blocked="Phase 1">
        <Step n={1} title="Invite staff">Name, email, and a role — the role decides what they can do. See the Roles tab for the full breakdown.</Step>
        <Step n={2} title="Designations">
          Job titles like "Teacher" or "Vice Principal" — attach one to each staff member (with an employee ID and hire date) if you plan to
          use payroll later.
        </Step>
        <Step n={3} title="Class Teachers">Assign one staff member per section as its Class Teacher, distinct from subject teachers.</Step>
      </Phase>
    </div>
  )
}

function ClassesTab() {
  return (
    <div className="flex flex-col gap-4">
      <Phase title="Phase 3 — Timetable &amp; subject assignments" blocked="Phases 1–2">
        <Step n={1} title="Class–Subject–Teacher assignments">
          For each section, decide which teacher teaches which subject — this is what makes "my classes" show up correctly for a
          Teacher/Class Teacher, and what exam-mark-entry and homework screens use to know who's allowed to grade what.
        </Step>
        <Step n={2} title="Timetable entries">
          Place each (section, subject, teacher) into an actual day/period/room slot. One entry per section+period+day — the system won't
          let you double-book a section's same period on the same day.
        </Step>
      </Phase>

      <Phase title="Phase 4 — Students &amp; Guardians" blocked="Phase 1 (sections)">
        <Step n={1} title="Admit students">
          Name, gender, date of birth, grade level, and section (must already exist). Admission automatically generates an admission
          number and records enrollment history.
        </Step>
        <Step n={2} title="Add guardians">
          Link a father/mother/guardian to each student, with one marked primary. Invite a guardian to give them a Parent login — they'll
          see their child's attendance, grades, invoices, and homework.
        </Step>
      </Phase>
    </div>
  )
}

function OperationsTab() {
  return (
    <div className="flex flex-col gap-4">
      <Phase title="Phase 5 — Attendance">
        <Step n={1} title="Nothing to configure">
          Go to Take Attendance, pick a section and date, mark each student present / absent / late / half-day / excused / on-leave. Staff
          Attendance works the same way.
        </Step>
      </Phase>

      <Phase title="Phase 6 — Grading &amp; Exams" blocked="Phase 3 (sections/subjects)">
        <Step n={1} title="Grading Scale + Bands">
          Define percentage ranges and their letter grades (A: 90–100, B: 80–89, ...). Mark one scale default — any exam subject that
          doesn't specify its own scale falls back to it.
        </Step>
        <Step n={2} title="Exams">Name, academic year, term, and a weight (used when averaging multiple exams into a final grade).</Step>
        <Step n={3} title="Exam Subjects">
          Which (subject, section) pairs are actually being tested, with max/passing marks and a date. An exam with no Exam Subject for a
          student's section won't show up for that student at all.
        </Step>
        <Step n={4} title="Enter &amp; publish marks">
          Marks entry opens once an exam subject exists. Publish when ready — students/parents can't see marks until then.
        </Step>
      </Phase>

      <Phase title="Phase 7 — Homework" blocked="Phase 3">
        <Step n={1} title="Create homework">Section, subject, teacher, due date, and max score. Submissions and grading happen per-student after.</Step>
      </Phase>
    </div>
  )
}

function FeesTab() {
  return (
    <div className="flex flex-col gap-4">
      <Phase title="Phase 8 — Fees &amp; Billing (your school billing its students)">
        <Step n={1} title="Fee Categories">Tuition, Transport, Library, Examination, and so on.</Step>
        <Step n={2} title="Fee Structures">Attach an amount and frequency to a category for the current year, optionally per grade level.</Step>
        <Step n={3} title="Student Fee Assignments (optional)">
          A per-student discount (percentage or fixed) against a fee structure — e.g. a sibling discount.{' '}
          <strong>Set this up before generating that student's invoice</strong> — discounts don't apply retroactively.
        </Step>
        <Step n={4} title="Invoices">Generate per-student, pulling in the relevant fee structures and any discount as line items.</Step>
        <Step n={5} title="Payments &amp; Credit Notes">
          Record money received (cash, cheque, bank transfer, UPI, card) — the invoice's status updates automatically. Issue a credit note
          for partial adjustments without altering the original invoice.
        </Step>
      </Phase>

      <Card>
        <CardHeader>
          <CardTitle>Phase 15 — Your own subscription</CardTitle>
          <CardDescription>This is separate from Phase 8 — it's what this platform charges your school, not what your school charges parents.</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">
            Under Settings → Billing you can see your current plan, trial end date, and usage against your plan's student/staff limits, and
            change plans. Creating a new staff user past your plan's limit returns a clear "plan limit reached" error rather than silently
            succeeding.
          </p>
        </CardContent>
      </Card>
    </div>
  )
}

function OtherModulesTab() {
  return (
    <div className="flex flex-col gap-4">
      <Phase title="Phase 9 — Library">
        <Step n={1} title="Add books">Title, author, ISBN, category, and copy count.</Step>
        <Step n={2} title="Issue &amp; return">
          Issue a copy to a student or staff member, then return it later. Fines (if any) are computed automatically from your Library fine
          settings, and available-copy counts adjust on issue/return.
        </Step>
      </Phase>

      <Phase title="Phase 10 — Transport">
        <Step n={1} title="Vehicles and Routes">Set up in either order — registration/driver details for vehicles, ordered stops for each route.</Step>
        <Step n={2} title="Student Assignments">
          Link a student to a route, stop, and vehicle. A student can only have one active assignment — assigning a new one automatically
          closes out the old one.
        </Step>
      </Phase>

      <Phase title="Phase 11 — Hostel">
        <Step n={1} title="Hostels">Boys / Girls / Mixed, each with a warden.</Step>
        <Step n={2} title="Rooms">Each with a bed capacity.</Step>
        <Step n={3} title="Allocations">
          Assign a student to a room + bed. Allocating past a room's capacity is rejected — vacate an existing allocation to free a bed
          first.
        </Step>
      </Phase>

      <Phase title="Phase 12 — HR &amp; Payroll" blocked="Phase 2 (designations)">
        <Step n={1} title="Salary Structures">
          Basic salary, allowances, deductions per staff member. Only one active structure per person — a new one automatically supersedes
          the old.
        </Step>
        <Step n={2} title="Payslips">
          Generate for a month/year — this snapshots the currently-active salary structure's numbers, so later salary changes don't rewrite
          old payslips.
        </Step>
        <Step n={3} title="Leave Types &amp; Requests">
          Define leave types (Casual, Sick, Earned, Unpaid) with an annual allowance. Staff apply, HR/Admin reviews. Approving a leave
          request automatically marks matching staff-attendance rows "on leave" for that date range — no need to mark attendance separately.
        </Step>
      </Phase>

      <Phase title="Phase 13 — Certificates">
        <Step n={1} title="Templates">
          Write a reusable body with placeholders: <code className="rounded bg-muted px-1 py-0.5 text-xs">{'{{student_name}}'}</code>,{' '}
          <code className="rounded bg-muted px-1 py-0.5 text-xs">{'{{admission_number}}'}</code>,{' '}
          <code className="rounded bg-muted px-1 py-0.5 text-xs">{'{{grade_level}}'}</code>,{' '}
          <code className="rounded bg-muted px-1 py-0.5 text-xs">{'{{section}}'}</code>,{' '}
          <code className="rounded bg-muted px-1 py-0.5 text-xs">{'{{school_name}}'}</code>,{' '}
          <code className="rounded bg-muted px-1 py-0.5 text-xs">{'{{date}}'}</code>.
        </Step>
        <Step n={2} title="Issue">
          Issue against a specific student — placeholders are interpolated and permanently snapshotted, so editing the template later never
          changes certificates already issued.
        </Step>
      </Phase>
    </div>
  )
}

function CommunicationTab() {
  return (
    <div className="flex flex-col gap-4">
      <Phase title="Phase 14 — Communication">
        <Step n={1} title="Notice Board">General notices or dated events, targeted at everyone / students / staff / parents, with an optional expiry.</Step>
        <Step n={2} title="Announcements">
          A broadcast that also creates an in-app notification for every matching recipient, delivered live via the notification bell where
          real-time delivery is configured — otherwise recipients still get it on their next automatic refresh.
        </Step>
        <Step n={3} title="Student Remarks">
          A teacher can leave academic, behavioral, or general notes on a student, optionally visible to that student's guardians.
        </Step>
      </Phase>
    </div>
  )
}

const ROLE_ROWS: { role: string; use: string; access: string }[] = [
  { role: 'School Admin', use: 'The account created at signup', access: 'Everything — users, roles, settings, billing, all modules' },
  { role: 'Principal', use: 'School leadership', access: 'Read-heavy across the board, manages academics/timetable/exams, no billing' },
  { role: 'Management', use: 'Trustees/owners who need visibility', access: 'Read-only across almost everything' },
  { role: 'Accountant', use: 'Fees/billing operations', access: 'Fee categories/structures, invoices, payments, credit notes' },
  { role: 'HR Staff', use: 'People operations', access: 'Users, designations, leave, payroll, staff attendance' },
  { role: 'Receptionist', use: 'Front desk', access: 'Student/guardian creation, visitor log' },
  { role: 'Teacher', use: 'Subject teacher', access: 'Their own classes: attendance, exam marks, homework, remarks' },
  { role: 'Class Teacher', use: 'Homeroom teacher', access: "Everything Teacher has, plus attendance export and section-wide remarks" },
  { role: 'Librarian', use: 'Library desk', access: 'Books and issues only' },
  { role: 'Transport Staff', use: 'Transport desk', access: 'Routes, vehicles, assignments only' },
  { role: 'Student', use: 'Enrolled student login', access: 'Read-only: own timetable, attendance, grades, homework, invoices, certificates' },
  { role: 'Parent', use: 'Guardian login (if invited)', access: 'Same as Student, scoped to their linked children' },
]

function RolesTab() {
  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-muted-foreground">
        A user can hold more than one role if your org chart doesn't map cleanly onto one of these — e.g. a Vice Principal who also
        teaches.
      </p>
      <Card>
        <CardContent className="overflow-x-auto pt-4 sm:pt-6">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border text-xs uppercase tracking-wide text-muted-foreground">
                <th className="py-2 pr-4">Role</th>
                <th className="py-2 pr-4">Typical use</th>
                <th className="py-2">Broad access</th>
              </tr>
            </thead>
            <tbody>
              {ROLE_ROWS.map((r) => (
                <tr key={r.role} className="border-b border-border last:border-0">
                  <td className="py-2 pr-4 font-medium">{r.role}</td>
                  <td className="py-2 pr-4 text-muted-foreground">{r.use}</td>
                  <td className="py-2 text-muted-foreground">{r.access}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>
  )
}

function FaqTab() {
  const items = [
    {
      q: '"Network error" or CORS-looking errors right after signing in.',
      a: "You're very likely on the wrong address — it must be your own {slug}.{domain} subdomain, never a bare central address. Re-check the URL bar.",
    },
    {
      q: '"Too Many Attempts" when logging in.',
      a: 'A short-lived safety limit on repeated login tries (protects against password guessing). It clears on its own within a minute — wait and try again rather than retrying rapidly.',
    },
    {
      q: 'A dropdown has no options in it.',
      a: "That almost always means an earlier setup phase is missing — e.g. the Student form's Section dropdown is empty because no Section has been created yet. Check the phase before the one you're on.",
    },
    {
      q: "A discount I set up isn't showing on an invoice.",
      a: 'Student Fee Assignment discounts only apply to invoices generated after the discount was created — they never apply retroactively to an existing invoice.',
    },
    {
      q: 'I created a new transport assignment / hostel allocation / salary structure and the old one disappeared.',
      a: "That's expected — these all follow a \"one active record per person\" rule, enforced by automatically closing out the previous one rather than blocking the new one.",
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      {items.map((item) => (
        <Card key={item.q}>
          <CardContent className="flex gap-3 pt-4 sm:pt-6">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-warning" />
            <div className="flex flex-col gap-1">
              <p className="text-sm font-medium">{item.q}</p>
              <p className="text-sm text-muted-foreground">{item.a}</p>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

export function HelpGuidePage() {
  const slug = window.location.hostname

  return (
    <div>
      <PageHeader
        title="Setup Guide"
        description="The complete, dependency-ordered flow for getting a new school running — what to set up first, and why."
      />

      <Tabs
        items={[
          { value: 'start', label: 'Getting Started', content: <GettingStartedTab slug={slug} /> },
          { value: 'classes', label: 'Classes & Students', content: <ClassesTab /> },
          { value: 'operations', label: 'Attendance & Exams', content: <OperationsTab /> },
          { value: 'fees', label: 'Fees & Billing', content: <FeesTab /> },
          { value: 'modules', label: 'Other Modules', content: <OtherModulesTab /> },
          { value: 'communication', label: 'Communication', content: <CommunicationTab /> },
          { value: 'roles', label: 'Roles & Permissions', content: <RolesTab /> },
          { value: 'faq', label: 'FAQ', content: <FaqTab /> },
        ]}
      />

      <p className="mt-8 flex items-center gap-1.5 text-xs text-muted-foreground">
        <ArrowRight className="h-3 w-3" />
        Every screen mentioned above lives in the sidebar to the left, grouped the same way as this guide.
      </p>
    </div>
  )
}
