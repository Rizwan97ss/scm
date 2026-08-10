import type { ReactNode } from 'react'
import { Menu } from 'lucide-react'
import { GlobalSearch } from './GlobalSearch'
import { NotificationBell } from './NotificationBell'
import { UserMenu } from './UserMenu'
import { Button } from '@/components/ui/Button'

export function Topbar({ onMenuClick, breadcrumbs }: { onMenuClick: () => void; breadcrumbs?: ReactNode }) {
  return (
    <header className="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-border bg-card px-4 sm:px-6">
      <div className="flex min-w-0 flex-1 items-center gap-3">
        <Button variant="ghost" size="icon" className="lg:hidden" onClick={onMenuClick} aria-label="Open navigation menu">
          <Menu className="h-5 w-5" />
        </Button>
        {breadcrumbs}
        <GlobalSearch />
      </div>
      <div className="flex shrink-0 items-center gap-2">
        <NotificationBell />
        <UserMenu />
      </div>
    </header>
  )
}
