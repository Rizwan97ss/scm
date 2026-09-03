import { useState } from 'react'
import { NavLink, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ChevronDown } from 'lucide-react'
import { usePermission } from '@/hooks/usePermission'
import { Dropdown } from '@/components/ui/Dropdown'
import { cn } from '@/utils/cn'
import { navLabelKey } from '@/utils/navLabel'
import type { NavGroupConfig } from '@/config/navigation'

const itemLinkClasses = ({ isActive }: { isActive: boolean }) =>
  cn(
    'flex items-center gap-3 rounded-md border-s-2 px-3 py-2 text-sm font-medium transition-colors',
    'hover:bg-muted',
    isActive ? 'border-primary bg-primary/10 text-primary' : 'border-transparent text-foreground'
  )

/**
 * Every group except the single flat "Overview" entry (see NavGroupConfig's
 * `flat` docblock) renders as a collapsible parent menu with its own icon —
 * expand/collapse in the full-width sidebar, or a flyout menu off the icon
 * when the sidebar is collapsed to its icon rail (see Sidebar.tsx's
 * `collapsed` state, threaded down here).
 */
export function RoleBasedNav({ groups, collapsed = false, onNavigate }: { groups: NavGroupConfig[]; collapsed?: boolean; onNavigate?: () => void }) {
  const { can } = usePermission()
  const { t } = useTranslation()
  const location = useLocation()
  const navigate = useNavigate()
  const [closedGroups, setClosedGroups] = useState<Set<string>>(new Set())

  function toggleGroup(label: string) {
    setClosedGroups((prev) => {
      const next = new Set(prev)
      if (next.has(label)) next.delete(label)
      else next.add(label)
      return next
    })
  }

  return (
    <nav className={cn('flex flex-col gap-1', collapsed ? 'items-center px-2 py-4' : 'px-3 py-4')} aria-label="Primary">
      {groups.map((group) => {
        const visibleItems = group.items.filter((item) => !item.permissions || can(...item.permissions))
        if (visibleItems.length === 0) return null

        const groupLabel = t(navLabelKey(group.label), group.label)

        if (group.flat) {
          return (
            <div key={group.label} className="flex w-full flex-col gap-1 pb-5">
              {!collapsed && <p className="px-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{groupLabel}</p>}
              {visibleItems.map((item) => {
                const itemLabel = t(navLabelKey(item.label), item.label)
                return (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    end={item.to === '/'}
                    onClick={onNavigate}
                    title={collapsed ? itemLabel : undefined}
                    aria-label={collapsed ? itemLabel : undefined}
                    className={itemLinkClasses}
                  >
                    {({ isActive }) => (
                      <>
                        <item.icon className={cn('h-4 w-4 shrink-0', isActive && 'text-primary')} />
                        {!collapsed && itemLabel}
                      </>
                    )}
                  </NavLink>
                )
              })}
            </div>
          )
        }

        const isGroupActive = visibleItems.some((item) => location.pathname === item.to || location.pathname.startsWith(`${item.to}/`))

        if (collapsed) {
          return (
            <Dropdown
              key={group.label}
              side="right"
              align="start"
              trigger={
                <button
                  type="button"
                  title={groupLabel}
                  aria-label={groupLabel}
                  className={cn(
                    'flex h-10 w-10 items-center justify-center rounded-md transition-colors hover:bg-muted',
                    isGroupActive ? 'text-primary' : 'text-foreground'
                  )}
                >
                  <group.icon className="h-5 w-5 shrink-0" />
                </button>
              }
              items={visibleItems.map((item) => ({
                label: t(navLabelKey(item.label), item.label),
                icon: <item.icon className="h-4 w-4" />,
                onSelect: () => {
                  navigate(item.to)
                  onNavigate?.()
                },
              }))}
            />
          )
        }

        const isOpen = !closedGroups.has(group.label)

        return (
          <div key={group.label} className="flex w-full flex-col gap-1">
            <button
              type="button"
              onClick={() => toggleGroup(group.label)}
              aria-expanded={isOpen}
              className={cn(
                'flex items-center gap-2 rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide transition-colors hover:bg-muted',
                isGroupActive ? 'text-primary' : 'text-muted-foreground'
              )}
            >
              <group.icon className="h-4 w-4 shrink-0" />
              <span className="flex-1 text-start">{groupLabel}</span>
              <ChevronDown className={cn('h-3.5 w-3.5 shrink-0 transition-transform', !isOpen && '-rotate-90 rtl:rotate-90')} />
            </button>
            {isOpen &&
              visibleItems.map((item) => {
                const itemLabel = t(navLabelKey(item.label), item.label)
                return (
                  <NavLink key={item.to} to={item.to} end={item.to === '/'} onClick={onNavigate} className={itemLinkClasses}>
                    {({ isActive }) => (
                      <>
                        <item.icon className={cn('h-4 w-4 shrink-0', isActive && 'text-primary')} />
                        {itemLabel}
                      </>
                    )}
                  </NavLink>
                )
              })}
          </div>
        )
      })}
    </nav>
  )
}
