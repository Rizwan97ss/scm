import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Check } from 'lucide-react'
import { plansApi } from '@/api/endpoints/plans'
import { queryKeys } from '@/api/queryKeys'
import { routePaths } from '@/routes/routePaths'
import { Reveal } from './Reveal'

export function PricingSection() {
  const { data: plans, isLoading } = useQuery({ queryKey: queryKeys.plans, queryFn: plansApi.list })

  return (
    <section className="bg-[var(--mk-paper)] py-24 sm:py-32">
      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <Reveal className="max-w-xl">
          <p className="mb-4 font-[var(--mk-font-mono)] text-[12px] uppercase tracking-[0.16em] text-[var(--mk-forest-deep)]">Pricing</p>
          <h2 className="text-[32px] font-medium leading-tight text-[var(--mk-ink)] sm:text-[40px]">One price. Every module included.</h2>
          <p className="mt-4 text-[16px] leading-relaxed text-[var(--mk-ink-soft)]">
            No feature paywalls, no per-module add-ons — every plan gets the whole system. Plans differ only by how many students and
            staff your school needs room for.
          </p>
        </Reveal>

        <div className="mt-14 grid gap-6 lg:grid-cols-3">
          {isLoading && [0, 1, 2].map((i) => <div key={i} className="h-[380px] animate-pulse rounded-2xl border border-[var(--mk-line-light)] bg-[var(--mk-card)]" />)}

          {plans?.map((plan, i) => {
            const featured = i === 1
            return (
              <Reveal key={plan.id} delay={i * 100}>
                <article
                  className={
                    featured
                      ? 'flex h-full flex-col rounded-2xl border-2 border-[var(--mk-marigold)] bg-[var(--mk-ink)] p-8 shadow-[0_25px_60px_-20px_rgba(231,169,62,0.35)]'
                      : 'flex h-full flex-col rounded-2xl border border-[var(--mk-line-light)] bg-[var(--mk-card)] p-8'
                  }
                >
                  {featured && (
                    <span className="mb-4 w-fit rounded-full bg-[var(--mk-marigold)] px-3 py-1 font-[var(--mk-font-mono)] text-[10.5px] font-semibold uppercase tracking-wide text-[var(--mk-marigold-ink)]">
                      Most popular
                    </span>
                  )}
                  <h3 className={featured ? 'text-[19px] font-medium text-[var(--mk-paper)]' : 'text-[19px] font-medium text-[var(--mk-ink)]'}>
                    {plan.name}
                  </h3>
                  <p className={featured ? 'mt-1 text-[13.5px] text-[var(--mk-mist)]' : 'mt-1 text-[13.5px] text-[var(--mk-ink-soft)]'}>
                    {plan.description}
                  </p>

                  <p className="mt-6">
                    <span className={featured ? 'font-[var(--mk-font-display)] text-4xl text-[var(--mk-paper)]' : 'font-[var(--mk-font-display)] text-4xl text-[var(--mk-ink)]'}>
                      ${(plan.price_cents / 100).toFixed(0)}
                    </span>
                    <span className={featured ? 'text-[14px] text-[var(--mk-mist-soft)]' : 'text-[14px] text-[var(--mk-ink-soft)]'}> /month</span>
                  </p>
                  <p className={featured ? 'mt-1 text-[12.5px] text-[var(--mk-mist-soft)]' : 'mt-1 text-[12.5px] text-[var(--mk-ink-soft)]'}>
                    {plan.trial_days}-day free trial
                  </p>

                  <ul className="mt-6 flex flex-1 flex-col gap-3 border-t border-[var(--mk-line-light)]/50 pt-6">
                    {[
                      plan.max_students ? `Up to ${plan.max_students} students` : 'Unlimited students',
                      plan.max_staff ? `Up to ${plan.max_staff} staff accounts` : 'Unlimited staff accounts',
                      'Every module included',
                      'Live notifications & role-based access',
                    ].map((line) => (
                      <li key={line} className={featured ? 'flex items-start gap-2.5 text-[13.5px] text-[var(--mk-mist)]' : 'flex items-start gap-2.5 text-[13.5px] text-[var(--mk-ink-soft)]'}>
                        <Check className={featured ? 'mt-0.5 h-4 w-4 shrink-0 text-[var(--mk-marigold)]' : 'mt-0.5 h-4 w-4 shrink-0 text-[var(--mk-forest-deep)]'} />
                        {line}
                      </li>
                    ))}
                  </ul>

                  <Link
                    to={routePaths.signup}
                    className={
                      featured
                        ? 'mt-8 rounded-full bg-[var(--mk-marigold)] py-3 text-center text-[14.5px] font-semibold text-[var(--mk-marigold-ink)] transition-transform hover:scale-[1.02]'
                        : 'mt-8 rounded-full border border-[var(--mk-ink)] py-3 text-center text-[14.5px] font-semibold text-[var(--mk-ink)] transition-colors hover:bg-[var(--mk-ink)] hover:text-[var(--mk-paper)]'
                    }
                  >
                    Start with {plan.name}
                  </Link>
                </article>
              </Reveal>
            )
          })}
        </div>
      </div>
    </section>
  )
}
