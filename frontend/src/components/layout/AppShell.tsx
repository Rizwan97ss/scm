import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import { Sidebar } from './Sidebar'
import { Topbar } from './Topbar'
import { RoleBasedNav } from './RoleBasedNav'
import { BillingStatusBanner } from './BillingStatusBanner'
import { Drawer } from '@/components/ui/Drawer'
import { useAuth } from '@/context/AuthContext'
import { useTheme } from '@/context/ThemeContext'
import { resolveNavGroups } from '@/config/navigation'

export function AppShell() {
  const [mobileNavOpen, setMobileNavOpen] = useState(false)
  const { hasRole } = useAuth()
  const { appName } = useTheme()
  const groups = resolveNavGroups(hasRole)

  return (
    <div className="flex h-svh overflow-hidden bg-background">
      <Sidebar />

      <Drawer open={mobileNavOpen} onOpenChange={setMobileNavOpen} title={appName} side="left">
        <RoleBasedNav groups={groups} onNavigate={() => setMobileNavOpen(false)} />
      </Drawer>

      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar onMenuClick={() => setMobileNavOpen(true)} />
        <BillingStatusBanner />
        <main className="flex-1 overflow-y-auto p-4 sm:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
