import * as RadixTabs from '@radix-ui/react-tabs'
import type { ReactNode } from 'react'
import { cn } from '@/utils/cn'

export interface TabItem {
  value: string
  label: string
  content: ReactNode
}

export function Tabs({ items, defaultValue, value, onValueChange }: { items: TabItem[]; defaultValue?: string; value?: string; onValueChange?: (value: string) => void }) {
  return (
    <RadixTabs.Root defaultValue={defaultValue ?? items[0]?.value} value={value} onValueChange={onValueChange}>
      <RadixTabs.List className="flex gap-1 border-b border-border">
        {items.map((item) => (
          <RadixTabs.Trigger
            key={item.value}
            value={item.value}
            className={cn(
              'border-b-2 border-transparent px-3 py-2 text-sm font-medium text-muted-foreground transition-colors',
              'hover:text-foreground data-[state=active]:border-primary data-[state=active]:text-primary',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-t-sm'
            )}
          >
            {item.label}
          </RadixTabs.Trigger>
        ))}
      </RadixTabs.List>
      {items.map((item) => (
        <RadixTabs.Content key={item.value} value={item.value} className="py-4">
          {item.content}
        </RadixTabs.Content>
      ))}
    </RadixTabs.Root>
  )
}
