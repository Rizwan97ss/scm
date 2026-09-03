import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { School, PanelLeftClose, PanelLeftOpen } from 'lucide-react'
import { RoleBasedNav } from './RoleBasedNav'
import { useTheme } from '@/context/ThemeContext'
import { useAuth } from '@/context/AuthContext'
import { resolveNavGroups } from '@/config/navigation'
import { routePaths } from '@/routes/routePaths'
import { cn } from '@/utils/cn'

const COLLAPSED_STORAGE_KEY = 'sms.sidebar-collapsed'

export function Sidebar() {
  const { t } = useTranslation()
  const { appName, logoUrl } = useTheme()
  const { hasRole } = useAuth()
  const groups = resolveNavGroups(hasRole)
  const [collapsed, setCollapsed] = useState(() => localStorage.getItem(COLLAPSED_STORAGE_KEY) === '1')

  function toggleCollapsed() {
    setCollapsed((prev) => {
      const next = !prev
      localStorage.setItem(COLLAPSED_STORAGE_KEY, next ? '1' : '0')
      return next
    })
  }

  return (
    <aside className={cn('hidden shrink-0 flex-col border-e border-border bg-card transition-[width] duration-200 lg:flex', collapsed ? 'w-16' : 'w-64')}>
      <div className={cn('flex items-center gap-2 border-b border-border px-4 py-4', collapsed && 'justify-center px-2')}>
        <Link to={routePaths.dashboard} className="flex min-w-0 flex-1 items-center gap-2" title={collapsed ? appName : undefined}>
          {logoUrl ? (
            <img src={logoUrl} alt={appName} className="h-8 w-8 shrink-0 rounded object-contain" />
          ) : (
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground">
              <School className="h-5 w-5" />
            </span>
          )}
          {!collapsed && <span className="truncate text-sm font-semibold">{appName}</span>}
        </Link>
      </div>

      <div className="flex-1 overflow-y-auto">
        <RoleBasedNav groups={groups} collapsed={collapsed} />
      </div>

      <div className={cn('border-t border-border p-2', collapsed && 'flex justify-center')}>
        <button
          type="button"
          onClick={toggleCollapsed}
          aria-label={collapsed ? t('common.sidebar.expand') : t('common.sidebar.collapse')}
          title={collapsed ? t('common.sidebar.expand') : t('common.sidebar.collapse')}
          className={cn(
            'flex items-center gap-2 rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground',
            collapsed ? 'h-10 w-10 justify-center px-0' : 'w-full'
          )}
        >
          {collapsed ? <PanelLeftOpen className="h-4 w-4 shrink-0" /> : <PanelLeftClose className="h-4 w-4 shrink-0" />}
          {!collapsed && t('common.sidebar.collapse')}
        </button>
      </div>
    </aside>
  )
}
