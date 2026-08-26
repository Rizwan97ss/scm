import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import { Sidebar } from './Sidebar'
import { Topbar } from './Topbar'
import { RoleBasedNav } from './RoleBasedNav'
import { BillingStatusBanner } from './BillingStatusBanner'
import { MfaSetupBanner } from './MfaSetupBanner'
import { Drawer } from '@/components/ui/Drawer'
import { useAuth } from '@/context/AuthContext'
import { useTheme } from '@/context/ThemeContext'
import { resolveNavGroups } from '@/config/navigation'
import { routePaths } from '@/routes/routePaths'
import { isInMfaGracePeriod } from '@/utils/mfaEnforcement'

export function AppShell() {
  const [mobileNavOpen, setMobileNavOpen] = useState(false)
  const { hasRole, user } = useAuth()
  const { appName } = useTheme()
  const groups = resolveNavGroups(hasRole)
  const mfaDaysRemaining =
    isInMfaGracePeriod(user) && user?.mfa_grace_period_ends_at
      ? Math.ceil((new Date(user.mfa_grace_period_ends_at).getTime() - Date.now()) / (24 * 60 * 60 * 1000))
      : null

  return (
    <div className="flex h-svh overflow-hidden bg-background">
      <Sidebar />

      <Drawer open={mobileNavOpen} onOpenChange={setMobileNavOpen} title={appName} side="left">
        <RoleBasedNav groups={groups} onNavigate={() => setMobileNavOpen(false)} />
      </Drawer>

      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar onMenuClick={() => setMobileNavOpen(true)} />
        <BillingStatusBanner />
        {mfaDaysRemaining !== null && <MfaSetupBanner setupPath={routePaths.mfaSetup} daysRemaining={mfaDaysRemaining} />}
        <main className="flex-1 overflow-y-auto p-4 sm:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
