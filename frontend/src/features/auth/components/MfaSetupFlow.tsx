import { useEffect, useState } from 'react'
import { Button, Card, CardContent, Checkbox, FormField, Input } from '@/components/ui'
import type { ApiError } from '@/api/client'
import type { MfaRecoveryCodesResponse, MfaSetupResponse } from '@/types/auth'

/**
 * Enrollment flow shared by the tenant MfaSetupPage and platform
 * PlatformMfaSetupPage — same 3 steps (QR -> confirm code -> save recovery
 * codes), different API functions per guard (see MfaChallengeService's
 * backend docblock for why the endpoints themselves aren't merged).
 */
export function MfaSetupFlow({
  onSetup,
  onConfirm,
  onDone,
}: {
  onSetup: () => Promise<MfaSetupResponse>
  onConfirm: (code: string) => Promise<MfaRecoveryCodesResponse>
  onDone: () => void
}) {
  const [step, setStep] = useState<'loading' | 'scan' | 'codes' | 'error'>('loading')
  const [setupData, setSetupData] = useState<MfaSetupResponse | null>(null)
  const [code, setCode] = useState('')
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([])
  const [confirmedSaved, setConfirmedSaved] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    let cancelled = false

    onSetup()
      .then((data) => {
        if (cancelled) return
        setSetupData(data)
        setStep('scan')
      })
      .catch((err) => {
        if (cancelled) return
        setError((err as ApiError).message ?? 'Unable to start MFA setup.')
        setStep('error')
      })

    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function handleConfirm() {
    setError(null)
    setIsSubmitting(true)
    try {
      const result = await onConfirm(code)
      setRecoveryCodes(result.recovery_codes)
      setStep('codes')
    } catch (err) {
      setError((err as ApiError).message ?? 'That code is incorrect.')
    } finally {
      setIsSubmitting(false)
    }
  }

  if (step === 'loading') {
    return <p className="text-sm text-muted-foreground">Setting up two-factor authentication…</p>
  }

  if (step === 'error') {
    return <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">{error}</p>
  }

  if (step === 'scan' && setupData) {
    return (
      <div className="flex flex-col gap-5">
        <div>
          <h2 className="text-lg font-semibold">1. Scan this code</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Use an authenticator app (Google Authenticator, Authy, 1Password, etc.) to scan this QR code.
          </p>
        </div>

        <img src={setupData.qr_code} alt="Two-factor authentication QR code" className="h-48 w-48 self-center rounded-md border border-border" />

        <FormField label="Can't scan it? Enter this key manually" htmlFor="mfa-manual-key">
          <Input id="mfa-manual-key" readOnly value={setupData.secret} className="font-mono text-xs" />
        </FormField>

        <div>
          <h2 className="text-lg font-semibold">2. Enter the code</h2>
          <p className="mt-1 text-sm text-muted-foreground">Enter the 6-digit code your app is now showing.</p>
        </div>

        {error && (
          <p role="alert" className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
            {error}
          </p>
        )}

        <FormField label="Authentication code" htmlFor="mfa-confirm-code" required>
          <Input
            id="mfa-confirm-code"
            autoFocus
            inputMode="numeric"
            placeholder="123456"
            value={code}
            onChange={(e) => setCode(e.target.value)}
          />
        </FormField>

        <Button onClick={handleConfirm} isLoading={isSubmitting} disabled={!code} className="self-start">
          Confirm & enable
        </Button>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h2 className="text-lg font-semibold">3. Save your recovery codes</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Each code can be used once if you lose access to your authenticator app. Save them somewhere safe — they won't be shown again.
        </p>
      </div>

      <Card>
        <CardContent className="grid grid-cols-2 gap-2 pt-6 font-mono text-sm">
          {recoveryCodes.map((recoveryCode) => (
            <span key={recoveryCode}>{recoveryCode}</span>
          ))}
        </CardContent>
      </Card>

      <label className="flex items-center gap-2 text-sm">
        <Checkbox checked={confirmedSaved} onCheckedChange={setConfirmedSaved} aria-label="I've saved these recovery codes" />
        I've saved these recovery codes somewhere safe.
      </label>

      <Button onClick={onDone} disabled={!confirmedSaved} className="self-start">
        Done
      </Button>
    </div>
  )
}
