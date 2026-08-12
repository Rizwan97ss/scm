import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { School } from 'lucide-react'
import { env } from '@/config/env'
import { cn } from '@/utils/cn'

const HIGHLIGHTS = ['Every module included, every plan', '12 built-in staff roles', 'Live notifications, not page refreshes']

/**
 * Shared shell for Login and Signup -- a dark brand panel (matching the
 * landing page's identity) beside a plain light form panel (see
 * .marketing-form-panel in index.css: it repaints the same --color-* tokens
 * Input/Button/FormField already read, so the actual form components are
 * unchanged, just re-themed).
 */
export function AuthLayout({
  eyebrow,
  title,
  description,
  children,
  footer,
  wide = false,
}: {
  eyebrow: string
  title: ReactNode
  description: string
  children: ReactNode
  footer?: ReactNode
  /** Widens the form panel for content that needs more room than a login form -- e.g. signup's side-by-side plan cards. */
  wide?: boolean
}) {
  return (
    <div className="marketing-scope grid min-h-svh lg:grid-cols-2">
      <div className="relative hidden flex-col justify-between overflow-hidden bg-[var(--mk-ink)] px-12 py-12 lg:flex">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0"
          style={{
            background: 'radial-gradient(60% 50% at 85% 15%, rgba(231,169,62,0.18), transparent 60%), radial-gradient(50% 45% at 10% 90%, rgba(63,143,120,0.18), transparent 60%)',
          }}
        />
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 opacity-[0.05]"
          style={{
            backgroundImage: 'linear-gradient(var(--mk-paper) 1px, transparent 1px), linear-gradient(90deg, var(--mk-paper) 1px, transparent 1px)',
            backgroundSize: '64px 64px',
          }}
        />

        <Link to="/" className="relative z-10 flex items-center gap-2.5">
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--mk-marigold)] text-[var(--mk-marigold-ink)]">
            <School className="h-4 w-4" />
          </span>
          <span className="font-[var(--mk-font-display)] text-[17px] font-medium text-[var(--mk-paper)]">{env.appName}</span>
        </Link>

        <div className="relative z-10 mk-reveal mk-in max-w-md">
          <p className="mb-4 font-[var(--mk-font-mono)] text-[12px] uppercase tracking-[0.16em] text-[var(--mk-marigold)]">{eyebrow}</p>
          <h1 className="text-[32px] font-medium leading-[1.15] text-[var(--mk-paper)]">{title}</h1>
          <p className="mt-4 text-[15px] leading-relaxed text-[var(--mk-mist)]">{description}</p>
        </div>

        <ul className="relative z-10 flex flex-col gap-3">
          {HIGHLIGHTS.map((h) => (
            <li key={h} className="flex items-center gap-2.5 text-[13.5px] text-[var(--mk-mist)]">
              <span className="h-1.5 w-1.5 rounded-full bg-[var(--mk-forest)]" />
              {h}
            </li>
          ))}
        </ul>
      </div>

      <div className="marketing-form-panel flex flex-col">
        <div className="flex items-center justify-between px-6 py-6 sm:px-10 lg:justify-end">
          <Link to="/" className="flex items-center gap-2 lg:hidden">
            <span className="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--mk-marigold)] text-[var(--mk-marigold-ink)]">
              <School className="h-3.5 w-3.5" />
            </span>
            <span className="font-[var(--mk-font-display)] text-[15px] font-medium text-[var(--mk-ink)]">{env.appName}</span>
          </Link>
        </div>

        <div className="flex flex-1 items-center justify-center px-6 pb-12 sm:px-10">
          <div className={cn('w-full transition-[max-width] duration-300', wide ? 'max-w-2xl' : 'max-w-sm')}>{children}</div>
        </div>

        {footer && <div className="px-6 pb-8 text-center sm:px-10">{footer}</div>}
      </div>
    </div>
  )
}
