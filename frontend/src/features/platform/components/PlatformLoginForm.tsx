import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate } from 'react-router-dom'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { platformLoginSchema, type PlatformLoginFormValues } from '../schemas/platformLoginSchema'
import { MfaChallengeForm } from '@/features/auth/components/MfaChallengeForm'
import { usePlatformAuth } from '@/context/PlatformAuthContext'
import { Button, Checkbox, FormField, Input } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import type { ApiError } from '@/api/client'

export function PlatformLoginForm() {
  const { t } = useTranslation()
  const { login, verifyMfaChallenge } = usePlatformAuth()
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)
  const [challengeToken, setChallengeToken] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<PlatformLoginFormValues>({
    resolver: zodResolver(platformLoginSchema),
    defaultValues: { email: '', password: '', remember: false },
  })

  function goToDestination() {
    navigate(routePaths.platformSchools, { replace: true })
  }

  async function onSubmit(values: PlatformLoginFormValues) {
    setFormError(null)
    try {
      const result = await login(values)
      if (result.mfa_required) {
        setChallengeToken(result.challenge_token)
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

      <FormField label={t('auth.email')} htmlFor="platform-email" error={errors.email?.message} required>
        <Input id="platform-email" type="email" autoComplete="username" invalid={!!errors.email} {...register('email')} />
      </FormField>

      <FormField label={t('auth.password')} htmlFor="platform-password" error={errors.password?.message} required>
        <Input id="platform-password" type="password" autoComplete="current-password" invalid={!!errors.password} {...register('password')} />
      </FormField>

      <label className="flex items-center gap-2 text-sm text-muted-foreground">
        <Checkbox checked={watch('remember')} onCheckedChange={(checked) => setValue('remember', checked)} aria-label={t('auth.rememberMe')} />
        {t('auth.rememberMe')}
      </label>

      <Button type="submit" isLoading={isSubmitting} className="mt-2">
        {t('auth.signIn')}
      </Button>
    </form>
  )
}
