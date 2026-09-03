import type { ReactNode } from 'react'
import * as RadixDropdown from '@radix-ui/react-dropdown-menu'
import { cn } from '@/utils/cn'

export interface DropdownItem {
  label: string
  icon?: ReactNode
  onSelect?: () => void
  destructive?: boolean
  disabled?: boolean
}

export function Dropdown({
  trigger,
  items,
  side = 'bottom',
  align = 'end',
}: {
  trigger: ReactNode
  items: DropdownItem[]
  side?: 'top' | 'right' | 'bottom' | 'left'
  align?: 'start' | 'center' | 'end'
}) {
  return (
    <RadixDropdown.Root>
      <RadixDropdown.Trigger asChild>{trigger}</RadixDropdown.Trigger>
      <RadixDropdown.Portal>
        <RadixDropdown.Content
          side={side}
          align={align}
          sideOffset={4}
          className="z-50 min-w-40 rounded-md border border-border bg-card p-1 text-foreground shadow-lg"
        >
          {items.map((item) => (
            <RadixDropdown.Item
              key={item.label}
              disabled={item.disabled}
              onSelect={item.onSelect}
              className={cn(
                'flex cursor-pointer select-none items-center gap-2 rounded-sm px-2.5 py-2 text-sm outline-none',
                'data-[highlighted]:bg-muted data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                item.destructive && 'text-destructive data-[highlighted]:bg-destructive/10'
              )}
            >
              {item.icon}
              {item.label}
            </RadixDropdown.Item>
          ))}
        </RadixDropdown.Content>
      </RadixDropdown.Portal>
    </RadixDropdown.Root>
  )
}
