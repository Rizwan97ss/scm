import { Reveal } from './Reveal'

const STEPS = [
  {
    n: '01',
    title: 'Create your school',
    body: 'Pick a plan and your school gets its own private database in seconds — 14 days free, no card charged until the trial ends.',
  },
  {
    n: '02',
    title: 'Set up your year',
    body: 'Add your academic year, grade levels and sections, then invite your staff and assign each of them one of 12 built-in roles.',
  },
  {
    n: '03',
    title: 'Go live, day one',
    body: 'Admit students, take the first attendance, send the first invoice — every module is ready the moment setup finishes.',
  },
]

export function HowItWorks() {
  return (
    <section className="relative overflow-hidden py-24 sm:py-32">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0"
        style={{ background: 'radial-gradient(45% 60% at 15% 20%, rgba(63,143,120,0.14), transparent 65%)' }}
      />
      <div className="relative mx-auto max-w-6xl px-5 sm:px-8">
        <Reveal className="max-w-xl">
          <p className="mb-4 font-[var(--mk-font-mono)] text-[12px] uppercase tracking-[0.16em] text-[var(--mk-marigold)]">Getting started</p>
          <h2 className="text-[32px] font-medium leading-tight text-[var(--mk-paper)] sm:text-[40px]">Live before lunch.</h2>
        </Reveal>

        <div className="mt-16 grid gap-10 sm:grid-cols-3 sm:gap-8">
          {STEPS.map((step, i) => (
            <Reveal key={step.n} delay={i * 120}>
              <div className="relative pl-1">
                <p className="font-[var(--mk-font-display)] text-[15px] text-[var(--mk-mist-soft)]">{step.n}</p>
                <h3 className="mt-3 text-[20px] font-medium text-[var(--mk-paper)]">{step.title}</h3>
                <p className="mt-2.5 text-[14.5px] leading-relaxed text-[var(--mk-mist)]">{step.body}</p>
                {i < STEPS.length - 1 && (
                  <div className="mt-8 hidden h-px w-full bg-gradient-to-r from-[var(--mk-line-dark)] to-transparent sm:block" aria-hidden />
                )}
              </div>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  )
}
