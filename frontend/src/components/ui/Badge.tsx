import type { ReactNode } from 'react'
import { cn } from '@/utils/cn'

export type BadgeVariant = 'default' | 'primary' | 'success' | 'warning' | 'destructive' | 'info' | 'outline'

const VARIANT_CLASSES: Record<BadgeVariant, string> = {
  default: 'bg-muted text-muted-foreground',
  primary: 'bg-primary/15 text-primary ring-1 ring-inset ring-primary/25',
  success: 'bg-success/15 text-success ring-1 ring-inset ring-success/25',
  warning: 'bg-warning/15 text-warning ring-1 ring-inset ring-warning/25',
  destructive: 'bg-destructive/15 text-destructive ring-1 ring-inset ring-destructive/25',
  info: 'bg-info/15 text-info ring-1 ring-inset ring-info/25',
  outline: 'border border-border text-foreground',
}

export function Badge({ variant = 'default', className, children }: { variant?: BadgeVariant; className?: string; children: ReactNode }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
        VARIANT_CLASSES[variant],
        className
      )}
    >
      {children}
    </span>
  )
}
