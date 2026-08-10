import type { ReactNode } from 'react'
import * as RadixTooltip from '@radix-ui/react-tooltip'

export function TooltipProvider({ children }: { children: ReactNode }) {
  return <RadixTooltip.Provider delayDuration={300}>{children}</RadixTooltip.Provider>
}

export function Tooltip({ content, children }: { content: ReactNode; children: ReactNode }) {
  return (
    <RadixTooltip.Root>
      <RadixTooltip.Trigger asChild>{children}</RadixTooltip.Trigger>
      <RadixTooltip.Portal>
        <RadixTooltip.Content
          sideOffset={6}
          className="z-50 max-w-xs rounded-md bg-secondary px-2.5 py-1.5 text-xs text-secondary-foreground shadow-md"
        >
          {content}
          <RadixTooltip.Arrow className="fill-secondary" />
        </RadixTooltip.Content>
      </RadixTooltip.Portal>
    </RadixTooltip.Root>
  )
}
