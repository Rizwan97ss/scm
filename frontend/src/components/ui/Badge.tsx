import type { ReactNode } from 'react'
import { cn } from '@/utils/cn'

export type BadgeVariant = 'default' | 'primary' | 'success' | 'warning' | 'destructive' | 'info' | 'outline'

const VARIANT_CLASSES: Record<BadgeVariant, string> = {
  default: 'bg-muted text-muted-foreground',
  primary: 'bg-primary/10 text-primary',
  success: 'bg-success/10 text-success',
  warning: 'bg-warning/10 text-warning',
  destructive: 'bg-destructive/10 text-destructive',
  info: 'bg-info/10 text-info',
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
