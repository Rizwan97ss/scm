import { Link } from 'react-router-dom'
import { ShieldAlert } from 'lucide-react'

/**
 * Nudges during the MFA grace window (see EnsureMfaEnrolled's backend
 * docblock) without blocking — RequireMfaSetup/RequirePlatformMfaSetup are
 * what actually hard-redirect once the grace period ends. Generic over
 * which zone's setup page to link to, so AppShell and PlatformShell can
 * both use it without a tenant-vs-platform branch inside this component.
 */
export function MfaSetupBanner({ setupPath, daysRemaining }: { setupPath: string; daysRemaining: number }) {
  return (
    <div className="flex items-center gap-2 border-b border-warning/20 bg-warning/10 px-4 py-2 text-sm text-warning sm:px-6">
      <ShieldAlert className="h-4 w-4 shrink-0" />
      <span className="flex-1">
        Two-factor authentication is required on this account —{' '}
        {daysRemaining <= 0 ? 'set it up now' : `you have ${daysRemaining} day${daysRemaining === 1 ? '' : 's'} left to set it up`}.
      </span>
      <Link to={setupPath} className="shrink-0 font-medium underline">
        Set up now
      </Link>
    </div>
  )
}
