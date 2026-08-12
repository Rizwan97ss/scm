import { useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import type { PlatformUser } from '@/types/platform'
import { confirmPlatformMfa, setupPlatformMfa } from '@/api/endpoints/platformAuth'
import { MfaSetupFlow } from '@/features/auth/components/MfaSetupFlow'
import { AuthLayout } from '@/features/marketing/components/AuthLayout'
import { usePlatformAuth } from '@/context/PlatformAuthContext'
import { routePaths } from '@/routes/routePaths'
import { queryKeys } from '@/api/queryKeys'

/** Landlord-side twin of MfaSetupPage — same flow, 'platform' guard. See MfaSetupPage's docblock for why this patches the cache synchronously rather than invalidating. */
export function PlatformMfaSetupPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { platformUser } = usePlatformAuth()

  function handleDone() {
    queryClient.setQueryData<PlatformUser>(queryKeys.platformMe, (old) => (old ? { ...old, mfa_enabled: true } : old))
    navigate(routePaths.platformSchools, { replace: true })
  }

  return (
    <AuthLayout
      eyebrow="Two-factor authentication"
      title="Secure your account."
      description="Platform access requires two-factor authentication — it takes about a minute to set up."
      wide
    >
      <p className="mb-6 text-sm text-muted-foreground">Signed in as {platformUser?.email}</p>
      <MfaSetupFlow onSetup={setupPlatformMfa} onConfirm={confirmPlatformMfa} onDone={handleDone} />
    </AuthLayout>
  )
}
