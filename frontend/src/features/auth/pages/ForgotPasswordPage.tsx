import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { forgotPassword } from '@/api/endpoints/auth'
import { Button, FormField, Input } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import type { ApiError } from '@/api/client'

export function ForgotPasswordPage() {
  const { t } = useTranslation()
  const [email, setEmail] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [sent, setSent] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    setIsSubmitting(true)
    setError(null)
    try {
      await forgotPassword(email)
      setSent(true)
    } catch (err) {
      setError((err as ApiError).message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-svh items-center justify-center bg-muted/40 px-4 py-12">
      <div className="w-full max-w-sm">
        <h1 className="mb-1 text-lg font-semibold">{t('auth.forgotPasswordHeading')}</h1>
        <p className="mb-6 text-sm text-muted-foreground">{t('auth.forgotPasswordDescription')}</p>

        <div className="rounded-lg border border-border bg-card p-6 shadow-sm">
          {sent ? (
            <p className="text-sm text-foreground">{t('auth.resetLinkSentMessage')}</p>
          ) : (
            <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
              {error && <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">{error}</p>}
              <FormField label={t('auth.email')} htmlFor="email" required>
                <Input id="email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
              </FormField>
              <Button type="submit" isLoading={isSubmitting}>
                {t('auth.sendResetLinkAction')}
              </Button>
            </form>
          )}
          <Link to={routePaths.login} className="mt-4 inline-block text-sm text-primary hover:underline">
            {t('auth.backToLoginLink')}
          </Link>
        </div>
      </div>
    </div>
  )
}
