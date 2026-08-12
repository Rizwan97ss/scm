import type { ComponentType } from 'react'
import {
  GraduationCap,
  Users,
  CalendarCheck,
  ClipboardList,
  Sigma,
  FileQuestion,
  Wallet,
  Receipt,
  BarChart3,
  BookMarked,
  Bus,
  Bed,
  Banknote,
  CalendarOff,
  IdCard,
  Newspaper,
  Megaphone,
  Bell,
} from 'lucide-react'
import { Reveal } from './Reveal'

interface Cluster {
  eyebrow: string
  title: string
  description: string
  items: { icon: ComponentType<{ className?: string }>; label: string }[]
}

const CLUSTERS: Cluster[] = [
  {
    eyebrow: '01 — People & academics',
    title: 'Every student, every class, in order.',
    description: 'Admissions, guardians, staff with granular roles, sections, subjects, timetables, and daily attendance — the structure everything else builds on.',
    items: [
      { icon: GraduationCap, label: 'Student admissions & enrollment' },
      { icon: Users, label: 'Guardians with their own parent login' },
      { icon: CalendarCheck, label: 'Student & staff attendance' },
    ],
  },
  {
    eyebrow: '02 — Assessment',
    title: 'From question bank to report card.',
    description: 'Configurable grading scales, exams scoped to a term, online tests, and marks that roll straight up into a published report card.',
    items: [
      { icon: ClipboardList, label: 'Exams & published report cards' },
      { icon: Sigma, label: 'Configurable grading scales' },
      { icon: FileQuestion, label: 'Online tests & question bank' },
    ],
  },
  {
    eyebrow: '03 — Money',
    title: 'Fees, invoices, and payments that reconcile.',
    description: 'Fee structures by grade level, per-student discounts, invoices that track partial payments automatically, and the reports finance actually asks for.',
    items: [
      { icon: Wallet, label: 'Fee structures & discounts' },
      { icon: Receipt, label: 'Invoices, payments & credit notes' },
      { icon: BarChart3, label: 'Financial reporting' },
    ],
  },
  {
    eyebrow: '04 — Operations',
    title: 'Library, transport, hostel — covered.',
    description: 'The parts of running a school that live outside the classroom, tracked with the same rigor as everything else.',
    items: [
      { icon: BookMarked, label: 'Library catalog & book issues' },
      { icon: Bus, label: 'Transport routes & assignments' },
      { icon: Bed, label: 'Hostel rooms & allocations' },
    ],
  },
  {
    eyebrow: '05 — People ops',
    title: 'Payroll and leave, handled properly.',
    description: 'Salary structures, generated payslips, and a leave workflow where approving a request updates attendance automatically.',
    items: [
      { icon: Banknote, label: 'Salary structures & payslips' },
      { icon: CalendarOff, label: 'Leave types & approvals' },
      { icon: IdCard, label: 'Staff ID cards, generated on demand' },
    ],
  },
  {
    eyebrow: '06 — Stay connected',
    title: 'News that actually reaches people.',
    description: 'Notices, announcements, and certificates — delivered live to the right audience the moment they go out, not on the next page refresh.',
    items: [
      { icon: Newspaper, label: 'Notice board for events & news' },
      { icon: Megaphone, label: 'Targeted announcements' },
      { icon: Bell, label: 'Real-time in-app notifications' },
    ],
  },
]

export function FeatureShowcase() {
  return (
    <section className="bg-[var(--mk-paper)] py-24 sm:py-32">
      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <Reveal className="max-w-2xl">
          <p className="mb-4 font-[var(--mk-font-mono)] text-[12px] uppercase tracking-[0.16em] text-[var(--mk-forest-deep)]">Everything, connected</p>
          <h2 className="text-[32px] font-medium leading-tight text-[var(--mk-ink)] sm:text-[40px]">
            Six departments. One record of truth.
          </h2>
          <p className="mt-4 text-[16px] leading-relaxed text-[var(--mk-ink-soft)]">
            Every module reads from the same student, staff, and academic-year data — so a promotion, a fee discount, or a role change
            shows up correctly everywhere at once, not in six separate spreadsheets.
          </p>
        </Reveal>

        <div className="mt-16 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {CLUSTERS.map((cluster, i) => (
            <Reveal key={cluster.title} delay={i * 90}>
              <article className="flex h-full flex-col rounded-2xl border border-[var(--mk-line-light)] bg-[var(--mk-card)] p-7 transition-shadow hover:shadow-[0_20px_45px_-20px_rgba(16,20,28,0.18)]">
                <p className="mb-3 font-[var(--mk-font-mono)] text-[11px] uppercase tracking-[0.1em] text-[var(--mk-forest-deep)]">{cluster.eyebrow}</p>
                <h3 className="text-[19px] font-medium leading-snug text-[var(--mk-ink)]">{cluster.title}</h3>
                <p className="mt-2.5 text-[14px] leading-relaxed text-[var(--mk-ink-soft)]">{cluster.description}</p>
                <ul className="mt-5 flex flex-col gap-2.5 border-t border-[var(--mk-line-light)] pt-5">
                  {cluster.items.map(({ icon: Icon, label }) => (
                    <li key={label} className="flex items-center gap-2.5 text-[13.5px] text-[var(--mk-ink)]">
                      <Icon className="h-4 w-4 shrink-0 text-[var(--mk-forest-deep)]" />
                      {label}
                    </li>
                  ))}
                </ul>
              </article>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}
