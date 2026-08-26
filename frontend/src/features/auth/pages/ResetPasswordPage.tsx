import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { resetPassword } from '@/api/endpoints/auth'
import { Button, FormField, Input } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import type { ApiError } from '@/api/client'
import { toast } from 'sonner'

export function ResetPasswordPage() {
  const { t } = useTranslation()
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''

  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    setIsSubmitting(true)
    setError(null)
    try {
      await resetPassword({ token, email, password, password_confirmation: passwordConfirmation })
      toast.success(t('auth.passwordResetToast'))
      navigate(routePaths.login)
    } catch (err) {
      setError((err as ApiError).message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-svh items-center justify-center bg-muted/40 px-4 py-12">
      <div className="w-full max-w-sm">
        <h1 className="mb-1 text-lg font-semibold">{t('auth.resetPasswordHeading')}</h1>
        <p className="mb-6 text-sm text-muted-foreground">{t('auth.resetPasswordForAccount', { email: email || t('auth.yourAccount') })}</p>

        <div className="rounded-lg border border-border bg-card p-6 shadow-sm">
          <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
            {error && <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">{error}</p>}
            <FormField label={t('auth.newPasswordLabel')} htmlFor="password" required>
              <Input id="password" type="password" required value={password} onChange={(e) => setPassword(e.target.value)} />
            </FormField>
            <FormField label={t('auth.confirmPassword')} htmlFor="password_confirmation" required>
              <Input
                id="password_confirmation"
                type="password"
                required
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
              />
            </FormField>
            <Button type="submit" isLoading={isSubmitting}>
              {t('auth.resetPasswordAction')}
            </Button>
          </form>
          <Link to={routePaths.login} className="mt-4 inline-block text-sm text-primary hover:underline">
            {t('auth.backToLoginLink')}
          </Link>
        </div>
      </div>
    </div>
  )
}
