import type { ReactNode } from 'react'
import * as Dialog from '@radix-ui/react-dialog'
import { X } from 'lucide-react'
import { cn } from '@/utils/cn'

export interface DrawerProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  children: ReactNode
  footer?: ReactNode
  side?: 'left' | 'right'
}

export function Drawer({ open, onOpenChange, title, children, footer, side = 'right' }: DrawerProps) {
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <Dialog.Overlay className="fixed inset-0 z-50 bg-black/50" />
        <Dialog.Content
          className={cn(
            'fixed top-0 z-50 flex h-full w-full max-w-md flex-col border-border bg-card shadow-xl',
            // 'left'/'right' describe the prop's intent (a nav drawer belongs
            // at the reading-start edge, a detail panel at the reading-end
            // edge) but render as logical start/end so the side that's
            // actually correct flips automatically under RTL instead of
            // pinning to a physical screen edge regardless of language.
            side === 'right' ? 'end-0 border-s' : 'start-0 border-e'
          )}
        >
          <div className="flex items-center justify-between gap-4 border-b border-border p-4">
            <Dialog.Title className="text-lg font-semibold">{title}</Dialog.Title>
            <Dialog.Close
              className="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              aria-label="Close panel"
            >
              <X className="h-4 w-4" />
            </Dialog.Close>
          </div>
          <div className="flex-1 overflow-y-auto p-4">{children}</div>
          {footer && <div className="flex justify-end gap-2 border-t border-border p-4">{footer}</div>}
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  )
}
