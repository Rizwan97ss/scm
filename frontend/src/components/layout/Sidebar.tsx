import { Link } from 'react-router-dom'
import { School } from 'lucide-react'
import { RoleBasedNav } from './RoleBasedNav'
import { useTheme } from '@/context/ThemeContext'
import { useAuth } from '@/context/AuthContext'
import { resolveNavGroups } from '@/config/navigation'
import { routePaths } from '@/routes/routePaths'

export function Sidebar() {
  const { appName, logoUrl } = useTheme()
  const { hasRole } = useAuth()
  const groups = resolveNavGroups(hasRole)

  return (
    <aside className="hidden w-64 shrink-0 flex-col border-e border-border bg-card lg:flex">
      <Link to={routePaths.dashboard} className="flex items-center gap-2 border-b border-border px-4 py-4">
        {logoUrl ? (
          <img src={logoUrl} alt={appName} className="h-8 w-8 rounded object-contain" />
        ) : (
          <span className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
            <School className="h-5 w-5" />
          </span>
        )}
        <span className="truncate text-sm font-semibold">{appName}</span>
      </Link>
      <div className="flex-1 overflow-y-auto">
        <RoleBasedNav groups={groups} />
      </div>
    </aside>
  )
}
