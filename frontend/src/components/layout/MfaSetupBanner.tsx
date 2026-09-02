import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ShieldAlert } from 'lucide-react'

/**
 * Nudges during the MFA grace window (see EnsureMfaEnrolled's backend
 * docblock) without blocking — RequireMfaSetup/RequirePlatformMfaSetup are
 * what actually hard-redirect once the grace period ends. Generic over
 * which zone's setup page to link to, so AppShell and PlatformShell can
 * both use it without a tenant-vs-platform branch inside this component.
 */
export function MfaSetupBanner({ setupPath, daysRemaining }: { setupPath: string; daysRemaining: number }) {
  const { t } = useTranslation()
  const action = daysRemaining <= 0 ? t('common.mfaBanner.setupNow') : t('common.mfaBanner.daysLeft', { count: daysRemaining })

  return (
    <div className="flex items-center gap-2 border-b border-warning/20 bg-warning/10 px-4 py-2 text-sm text-warning sm:px-6">
      <ShieldAlert className="h-4 w-4 shrink-0" />
      <span className="flex-1">{t('common.mfaBanner.required', { action })}</span>
      <Link to={setupPath} className="shrink-0 font-medium underline">
        {t('common.mfaBanner.link')}
      </Link>
    </div>
  )
}
