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
            side === 'right' ? 'right-0 border-l' : 'left-0 border-r'
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
