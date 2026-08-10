import type { HTMLAttributes, ReactNode } from 'react'
import { cn } from '@/utils/cn'

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('rounded-lg border border-border bg-card text-card-foreground shadow-sm', className)} {...props} />
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

export function StatCard({
  label,
  value,
  icon,
  trend,
}: {
  label: string
  value: ReactNode
  icon?: ReactNode
  trend?: { direction: 'up' | 'down'; label: string }
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
        {icon && <div className="rounded-full bg-primary/10 p-3 text-primary">{icon}</div>}
      </CardContent>
    </Card>
  )
}
