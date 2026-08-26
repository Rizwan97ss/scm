import { useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import type { User } from '@/types/auth'
import { confirmMfa, setupMfa } from '@/api/endpoints/auth'
import { MfaSetupFlow } from '../components/MfaSetupFlow'
import { AuthLayout } from '@/features/marketing/components/AuthLayout'
import { useAuth } from '@/context/AuthContext'
import { routePaths } from '@/routes/routePaths'
import { queryKeys } from '@/api/queryKeys'

/**
 * Reached two ways: voluntarily (a banner link during the grace period) or
 * by force (RequireMfaSetup redirecting once the grace period is over — see
 * its own docblock). No permission gate: every role needs to be able to
 * reach this, the same self-service shape as the password page.
 */
export function MfaSetupPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { user } = useAuth()

  function handleDone() {
    // Patch the cache synchronously, not invalidateQueries() — that only
    // triggers a background refetch, and navigate() below would fire
    // before it resolves, so RequireMfaSetup's very next render would
    // still see the stale mfa_enabled: false and immediately bounce back
    // here (confirmed live: this exact race sent the browser right back
    // to /mfa/setup instead of the dashboard).
    queryClient.setQueryData<User>(queryKeys.me, (old) => (old ? { ...old, mfa_enabled: true } : old))
    navigate(routePaths.dashboard, { replace: true })
  }

  return (
    <AuthLayout eyebrow={t('auth.mfaHeading')} title={t('auth.mfaSetupTitle')} description={t('auth.mfaSetupDescription')} wide>
      <p className="mb-6 text-sm text-muted-foreground">{t('auth.signedInAs', { email: user?.email })}</p>
      <MfaSetupFlow onSetup={setupMfa} onConfirm={confirmMfa} onDone={handleDone} />
    </AuthLayout>
  )
}
