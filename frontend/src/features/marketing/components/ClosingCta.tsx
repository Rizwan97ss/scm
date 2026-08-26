import { Link } from 'react-router-dom'
import { ArrowRight } from 'lucide-react'
import { routePaths } from '@/routes/routePaths'
import { Reveal } from './Reveal'

export function ClosingCta() {
  return (
    <section className="relative overflow-hidden py-24 sm:py-32">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0"
        style={{ background: 'radial-gradient(60% 80% at 50% 100%, rgba(231,169,62,0.14), transparent 65%)' }}
      />
      <div className="relative mx-auto max-w-3xl px-5 text-center sm:px-8">
        <Reveal>
          <h2 className="text-[34px] font-medium leading-[1.15] text-[var(--mk-paper)] sm:text-[46px]">
            Your school's next term starts <em className="not-italic text-[var(--mk-marigold)]">organized.</em>
          </h2>
          <p className="mx-auto mt-5 max-w-md text-[16px] leading-relaxed text-[var(--mk-mist)]">
            Set up takes minutes. The trial is free for 14 days, and nothing is charged until it ends.
          </p>
          <Link
            to={routePaths.signup}
            className="group mt-9 inline-flex items-center gap-2 rounded-full bg-[var(--mk-marigold)] px-7 py-3.5 text-[15px] font-semibold text-[var(--mk-marigold-ink)] transition-transform hover:scale-[1.02] active:scale-[0.98]"
          >
            Start your free trial
            <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
          </Link>
        </Reveal>
      </div>
    </section>
  )
}
