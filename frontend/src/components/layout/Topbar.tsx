import type { ReactNode } from 'react'
import { Menu } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { GlobalSearch } from './GlobalSearch'
import { LanguageSwitcher } from './LanguageSwitcher'
import { NotificationBell } from './NotificationBell'
import { ThemeSwitcher } from './ThemeSwitcher'
import { UserMenu } from './UserMenu'
import { Button } from '@/components/ui/Button'

export function Topbar({ onMenuClick, breadcrumbs }: { onMenuClick: () => void; breadcrumbs?: ReactNode }) {
  const { t } = useTranslation()
  return (
    <header className="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-border bg-card px-4 sm:px-6">
      <div className="flex min-w-0 flex-1 items-center gap-3">
        <Button variant="ghost" size="icon" className="lg:hidden" onClick={onMenuClick} aria-label={t('common.topbar.openMenu')}>
          <Menu className="h-5 w-5" />
        </Button>
        {breadcrumbs}
        <GlobalSearch />
      </div>
      <div className="flex shrink-0 items-center gap-2">
        <LanguageSwitcher />
        <ThemeSwitcher />
        <NotificationBell />
        <UserMenu />
      </div>
    </header>
  )
}
