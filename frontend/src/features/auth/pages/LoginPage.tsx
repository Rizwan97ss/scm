import { useState } from 'react'
import { Link } from 'react-router-dom'
import { LoginForm } from '../components/LoginForm'
import { AuthLayout } from '@/features/marketing/components/AuthLayout'
import { routePaths } from '@/routes/routePaths'

const STEP_COPY = {
  credentials: { heading: 'Sign in', description: 'Enter your email and password to continue.' },
  mfa: { heading: 'Two-factor authentication', description: 'Confirm it\'s you to finish signing in.' },
} as const

export function LoginPage() {
  const [step, setStep] = useState<'credentials' | 'mfa'>('credentials')

  return (
    <AuthLayout
      eyebrow="Welcome back"
      title="Sign in to your school."
      description="Every school runs on its own private database — you're signing in to yours, not a shared one."
      footer={
        <p className="text-[13.5px] text-[var(--mk-ink-soft)]">
          New here?{' '}
          <Link to={routePaths.signup} className="font-medium text-[var(--mk-forest-deep)] hover:underline">
            Start a 14-day free trial
          </Link>
        </p>
      }
    >
      <h2 className="mb-1 text-[22px] font-medium text-[var(--mk-ink)]">{STEP_COPY[step].heading}</h2>
      <p className="mb-7 text-[14px] text-[var(--mk-ink-soft)]">{STEP_COPY[step].description}</p>
      <LoginForm onStepChange={setStep} />
    </AuthLayout>
  )
}
