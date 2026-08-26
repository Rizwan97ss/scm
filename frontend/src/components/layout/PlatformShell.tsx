import { NavLink, Outlet } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { BarChart3, LogOut, School, ShieldCheck } from 'lucide-react'
import { usePlatformAuth } from '@/context/PlatformAuthContext'
import { Button } from '@/components/ui'
import { LanguageSwitcher } from './LanguageSwitcher'
import { MfaSetupBanner } from './MfaSetupBanner'
import { ThemeSwitcher } from './ThemeSwitcher'
import { routePaths } from '@/routes/routePaths'
import { cn } from '@/utils/cn'
import { isInMfaGracePeriod } from '@/utils/mfaEnforcement'

const NAV_ITEMS = [
  { labelKey: 'platform.navSchools', to: routePaths.platformSchools, icon: School },
  { labelKey: 'platform.navMetrics', to: routePaths.platformMetrics, icon: BarChart3 },
]

/**
 * Deliberately its own shell, not a reuse of the tenant AppShell — that one
 * is driven by ThemeContext (one tenant's branding) and useAuth's
 * roles/permissions, neither of which apply to a PlatformUser at all.
 */
export function PlatformShell() {
  const { t } = useTranslation()
  const { platformUser, logout } = usePlatformAuth()
  const mfaDaysRemaining =
    isInMfaGracePeriod(platformUser) && platformUser?.mfa_grace_period_ends_at
      ? Math.ceil((new Date(platformUser.mfa_grace_period_ends_at).getTime() - Date.now()) / (24 * 60 * 60 * 1000))
      : null

  return (
    <div className="flex min-h-svh flex-col bg-muted/40">
      {mfaDaysRemaining !== null && <MfaSetupBanner setupPath={routePaths.platformMfaSetup} daysRemaining={mfaDaysRemaining} />}
      <header className="border-b border-border bg-card">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
          <div className="flex items-center gap-6">
            <span className="flex items-center gap-2 text-sm font-semibold">
              <ShieldCheck className="h-5 w-5 text-primary" />
              {t('platform.consoleTitle')}
            </span>
            <nav className="flex items-center gap-1">
              {NAV_ITEMS.map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  className={({ isActive }) =>
                    cn(
                      'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground',
                      isActive && 'bg-accent text-foreground'
                    )
                  }
                >
                  <item.icon className="h-4 w-4" />
                  {t(item.labelKey)}
                </NavLink>
              ))}
            </nav>
          </div>
          <div className="flex items-center gap-3">
            <ThemeSwitcher />
            <LanguageSwitcher />
            <span className="text-sm text-muted-foreground">{platformUser?.full_name}</span>
            <Button variant="ghost" size="sm" onClick={() => void logout()}>
              <LogOut className="h-4 w-4" />
              {t('common.logout')}
            </Button>
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6">
        <Outlet />
      </main>
    </div>
  )
}
