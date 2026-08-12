import { Link } from 'react-router-dom'
import { ArrowRight, CalendarCheck, Receipt, ClipboardCheck } from 'lucide-react'
import { routePaths } from '@/routes/routePaths'

const FLOAT_CARDS = [
  { icon: CalendarCheck, label: 'Attendance', value: '96.4%', sub: 'Present today', style: { top: '6%', right: '2%' }, delay: '0s' },
  { icon: Receipt, label: 'Invoices', value: '$27,380', sub: 'Collected this month', style: { top: '46%', right: '14%' }, delay: '0.6s' },
  { icon: ClipboardCheck, label: 'Exams', value: '2 published', sub: 'Midterm · Final', style: { top: '76%', right: '0%' }, delay: '1.2s' },
]

export function Hero() {
  return (
    <section className="relative overflow-hidden pb-24 pt-40 sm:pb-32 sm:pt-48">
      {/* Ambient background: soft radial glow + faint structural grid, both purely decorative */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0"
        style={{
          background:
            'radial-gradient(60% 50% at 78% 12%, rgba(231,169,62,0.16), transparent 60%), radial-gradient(50% 40% at 8% 85%, rgba(63,143,120,0.16), transparent 60%)',
        }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 opacity-[0.05]"
        style={{
          backgroundImage: 'linear-gradient(var(--mk-paper) 1px, transparent 1px), linear-gradient(90deg, var(--mk-paper) 1px, transparent 1px)',
          backgroundSize: '64px 64px',
          maskImage: 'linear-gradient(to bottom, transparent, black 15%, black 70%, transparent)',
        }}
      />

      <div className="relative mx-auto grid max-w-6xl gap-16 px-5 sm:px-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
        <div className="mk-reveal mk-in" style={{ animationDelay: '0.05s' }}>
          <p className="mb-5 font-[var(--mk-font-mono)] text-[12px] uppercase tracking-[0.16em] text-[var(--mk-forest)]">
            School management, run from one place
          </p>
          <h1 className="max-w-xl text-[42px] font-medium leading-[1.08] text-[var(--mk-paper)] sm:text-[58px]">
            The whole school, <em className="not-italic text-[var(--mk-marigold)]">running as one system.</em>
          </h1>
          <p className="mt-6 max-w-md text-[17px] leading-relaxed text-[var(--mk-mist)]">
            From morning roll call to year-end report cards — admissions, attendance, exams, fees, payroll, library, transport, and hostel,
            in a single connected system built for every role, from Principal to Parent.
          </p>

          <div className="mt-9 flex flex-wrap items-center gap-4">
            <Link
              to={routePaths.signup}
              className="group inline-flex items-center gap-2 rounded-full bg-[var(--mk-marigold)] px-6 py-3.5 text-[15px] font-semibold text-[var(--mk-marigold-ink)] transition-transform hover:scale-[1.02] active:scale-[0.98]"
            >
              Start your 14-day free trial
              <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
            </Link>
            <Link to={routePaths.login} className="text-[15px] font-medium text-[var(--mk-paper)] underline decoration-[var(--mk-mist-soft)] underline-offset-4 hover:decoration-[var(--mk-paper)]">
              Sign in to your school
            </Link>
          </div>

          <p className="mt-6 font-[var(--mk-font-mono)] text-[12px] uppercase tracking-[0.1em] text-[var(--mk-mist-soft)]">
            No card charged until your trial ends · 12 built-in staff roles · Cancel anytime
          </p>
        </div>

        {/* Floating module cards -- an abstracted glimpse of the product, not a literal screenshot */}
        <div className="relative hidden h-[420px] lg:block" aria-hidden>
          {FLOAT_CARDS.map(({ icon: Icon, label, value, sub, style, delay }) => (
            <div
              key={label}
              className="absolute w-[220px] rounded-2xl border border-[var(--mk-line-dark)] bg-[var(--mk-ink-raised)] p-4 shadow-[0_30px_60px_-15px_rgba(0,0,0,0.5)]"
              style={{ ...style, animation: `mk-drift 9s ease-in-out ${delay} infinite` }}
            >
              <div className="mb-3 flex items-center gap-2">
                <span className="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--mk-forest-deep)]/30 text-[var(--mk-forest)]">
                  <Icon className="h-3.5 w-3.5" />
                </span>
                <span className="font-[var(--mk-font-mono)] text-[11px] uppercase tracking-wide text-[var(--mk-mist-soft)]">{label}</span>
              </div>
              <p className="font-[var(--mk-font-display)] text-2xl text-[var(--mk-paper)]">{value}</p>
              <p className="mt-0.5 text-[12.5px] text-[var(--mk-mist-soft)]">{sub}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
