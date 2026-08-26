import type { HTMLAttributes, ReactNode } from 'react'
import { cn } from '@/utils/cn'

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('rounded-xl border border-border bg-card text-card-foreground shadow-sm transition-shadow hover:shadow-md', className)} {...props} />
}

export function CardHeader({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('flex flex-col gap-1 p-4 sm:p-6', className)} {...props} />
}

export function CardTitle({ className, ...props }: HTMLAttributes<HTMLHeadingElement>) {
  return <h3 className={cn('text-lg font-semibold leading-none', className)} {...props} />
}

export function CardDescription({ className, ...props }: HTMLAttributes<HTMLParagraphElement>) {
  return <p className={cn('text-sm text-muted-foreground', className)} {...props} />
}

export function CardContent({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('p-4 pt-0 sm:p-6 sm:pt-0', className)} {...props} />
}

export function CardFooter({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('flex items-center gap-2 p-4 pt-0 sm:p-6 sm:pt-0', className)} {...props} />
}

export type StatTone = 'primary' | 'success' | 'warning' | 'destructive' | 'info' | 'violet' | 'rose' | 'cyan'

const STAT_TONE_CLASSES: Record<StatTone, string> = {
  primary: 'bg-primary/10 text-primary',
  success: 'bg-success/10 text-success',
  warning: 'bg-warning/10 text-warning',
  destructive: 'bg-destructive/10 text-destructive',
  info: 'bg-info/10 text-info',
  violet: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
  rose: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
  cyan: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
}

export function StatCard({
  label,
  value,
  icon,
  trend,
  tone = 'primary',
}: {
  label: string
  value: ReactNode
  icon?: ReactNode
  trend?: { direction: 'up' | 'down'; label: string }
  tone?: StatTone
}) {
  return (
    <Card>
      <CardContent className="flex items-center justify-between gap-4 pt-4 sm:pt-6">
        <div className="flex flex-col gap-1">
          <span className="text-sm text-muted-foreground">{label}</span>
          <span className="text-2xl font-semibold">{value}</span>
          {trend && (
            <span className={cn('text-xs', trend.direction === 'up' ? 'text-success' : 'text-destructive')}>{trend.label}</span>
          )}
        </div>
        {icon && <div className={cn('rounded-full p-3', STAT_TONE_CLASSES[tone])}>{icon}</div>}
      </CardContent>
    </Card>
  )
}
