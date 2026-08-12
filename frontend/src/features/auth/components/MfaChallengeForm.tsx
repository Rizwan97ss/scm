import { useState, type FormEvent } from 'react'
import { Button, FormField, Input } from '@/components/ui'
import type { MfaChallengePayload } from '@/types/auth'
import type { ApiError } from '@/api/client'

/**
 * The login flow's second step, shared between the tenant LoginForm and
 * PlatformLoginForm — same shape, different guard, see MfaChallengeService's
 * backend docblock for why the endpoints themselves aren't merged. The
 * caller supplies onVerify (which zone's API function to call) and
 * onSuccess (where to navigate once the returned user is cached).
 */
export function MfaChallengeForm({
  challengeToken,
  onVerify,
  onSuccess,
}: {
  challengeToken: string
  onVerify: (payload: MfaChallengePayload) => Promise<unknown>
  onSuccess: () => void
}) {
  const [code, setCode] = useState('')
  const [useRecoveryCode, setUseRecoveryCode] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setError(null)
    setIsSubmitting(true)
    try {
      await onVerify({ challenge_token: challengeToken, code })
      onSuccess()
    } catch (err) {
      setError((err as ApiError).message ?? 'Unable to verify that code.')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
      <p className="text-sm text-muted-foreground">
        {useRecoveryCode
          ? 'Enter one of the recovery codes you saved when you set up two-factor authentication.'
          : 'Enter the 6-digit code from your authenticator app.'}
      </p>

      {error && (
        <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error}
        </p>
      )}

      <FormField label={useRecoveryCode ? 'Recovery code' : 'Authentication code'} htmlFor="mfa-code" required>
        <Input
          id="mfa-code"
          autoFocus
          autoComplete="one-time-code"
          inputMode={useRecoveryCode ? 'text' : 'numeric'}
          placeholder={useRecoveryCode ? 'xxxx-xxxx-xxxx' : '123456'}
          value={code}
          onChange={(event) => setCode(event.target.value)}
        />
      </FormField>

      <button
        type="button"
        onClick={() => {
          setUseRecoveryCode((v) => !v)
          setCode('')
          setError(null)
        }}
        className="text-left text-sm text-primary hover:underline"
      >
        {useRecoveryCode ? 'Use your authenticator app instead' : 'Use a recovery code instead'}
      </button>

      <Button type="submit" isLoading={isSubmitting} disabled={!code} className="mt-2">
        Verify
      </Button>
    </form>
  )
}
