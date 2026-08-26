import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate, useLocation, Link } from 'react-router-dom'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { loginSchema, type LoginFormValues } from '../schemas/loginSchema'
import { MfaChallengeForm } from './MfaChallengeForm'
import { useAuth } from '@/context/AuthContext'
import { Button, Checkbox, FormField, Input } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import type { ApiError } from '@/api/client'

export function LoginForm({ onStepChange }: { onStepChange?: (step: 'credentials' | 'mfa') => void }) {
  const { t } = useTranslation()
  const { login, verifyMfaChallenge } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [formError, setFormError] = useState<string | null>(null)
  const [challengeToken, setChallengeToken] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '', remember: false },
  })

  function goToDestination() {
    const redirectTo = (location.state as { from?: Location })?.from?.pathname ?? routePaths.dashboard
    navigate(redirectTo, { replace: true })
  }

  async function onSubmit(values: LoginFormValues) {
    setFormError(null)
    try {
      const result = await login(values)
      if (result.mfa_required) {
        setChallengeToken(result.challenge_token)
        onStepChange?.('mfa')
        return
      }
      goToDestination()
    } catch (error) {
      setFormError((error as ApiError).message ?? t('auth.unableToLogIn'))
    }
  }

  if (challengeToken) {
    return <MfaChallengeForm challengeToken={challengeToken} onVerify={verifyMfaChallenge} onSuccess={goToDestination} />
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
      {formError && (
        <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {formError}
        </p>
      )}

      <FormField label={t('auth.emailOrUsername')} htmlFor="email" error={errors.email?.message} required>
        <Input id="email" autoComplete="username" invalid={!!errors.email} {...register('email')} />
      </FormField>

      <FormField label={t('auth.password')} htmlFor="password" error={errors.password?.message} required>
        <Input id="password" type="password" autoComplete="current-password" invalid={!!errors.password} {...register('password')} />
      </FormField>

      <div className="flex items-center justify-between">
        <label className="flex items-center gap-2 text-sm text-muted-foreground">
          <Checkbox checked={watch('remember')} onCheckedChange={(checked) => setValue('remember', checked)} aria-label={t('auth.rememberMe')} />
          {t('auth.rememberMe')}
        </label>
        <Link to={routePaths.forgotPassword} className="text-sm text-primary hover:underline">
          {t('auth.forgotPassword')}
        </Link>
      </div>

      <Button type="submit" isLoading={isSubmitting} className="mt-2">
        {t('auth.signIn')}
      </Button>
    </form>
  )
}
