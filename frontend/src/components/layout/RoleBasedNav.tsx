import { NavLink } from 'react-router-dom'
import { usePermission } from '@/hooks/usePermission'
import { cn } from '@/utils/cn'
import type { NavGroupConfig } from '@/config/navigation'

export function RoleBasedNav({ groups, onNavigate }: { groups: NavGroupConfig[]; onNavigate?: () => void }) {
  const { can } = usePermission()

  return (
    <nav className="flex flex-col gap-6 px-3 py-4" aria-label="Primary">
      {groups.map((group) => {
        const visibleItems = group.items.filter((item) => !item.permissions || can(...item.permissions))
        if (visibleItems.length === 0) return null

        return (
          <div key={group.label} className="flex flex-col gap-1">
            <p className="px-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{group.label}</p>
            {visibleItems.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                onClick={onNavigate}
                className={({ isActive }) =>
                  cn(
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                    'hover:bg-muted',
                    isActive ? 'bg-primary/10 text-primary' : 'text-foreground'
                  )
                }
              >
                <item.icon className="h-4 w-4 shrink-0" />
                {item.label}
              </NavLink>
            ))}
          </div>
        )
      })}
    </nav>
  )
}
