import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { LoginForm } from '../components/LoginForm'
import { AuthLayout } from '@/features/marketing/components/AuthLayout'
import { routePaths } from '@/routes/routePaths'

export function LoginPage() {
  const { t } = useTranslation()
  const [step, setStep] = useState<'credentials' | 'mfa'>('credentials')

  const stepCopy = {
    credentials: { heading: t('auth.signIn'), description: t('auth.signInDescription') },
    mfa: { heading: t('auth.mfaHeading'), description: t('auth.mfaDescription') },
  } as const

  return (
    <AuthLayout
      eyebrow={t('auth.welcomeBack')}
      title={t('auth.signInToYourSchool')}
      description={t('auth.signInHint')}
      footer={
        <p className="text-[13.5px] text-[var(--mk-ink-soft)]">
          {t('auth.newHere')}{' '}
          <Link to={routePaths.signup} className="font-medium text-[var(--mk-forest-deep)] hover:underline">
            {t('auth.startFreeTrialLink')}
          </Link>
        </p>
      }
    >
      <h2 className="mb-1 text-[22px] font-medium text-[var(--mk-ink)]">{stepCopy[step].heading}</h2>
      <p className="mb-7 text-[14px] text-[var(--mk-ink-soft)]">{stepCopy[step].description}</p>
      <LoginForm onStepChange={setStep} />
    </AuthLayout>
  )
}
