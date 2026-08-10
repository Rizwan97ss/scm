import type { BadgeVariant } from '@/components/ui'
import type { AttendanceStatus } from '@/types/enums'

export const ATTENDANCE_STATUS_BADGE_VARIANT: Record<AttendanceStatus, BadgeVariant> = {
  present: 'success',
  absent: 'destructive',
  late: 'warning',
  half_day: 'warning',
  excused: 'info',
  on_leave: 'default',
}

export const ATTENDANCE_STATUS_ACTIVE_CLASSES: Record<BadgeVariant, string> = {
  success: 'bg-success text-success-foreground border-success',
  destructive: 'bg-destructive text-destructive-foreground border-destructive',
  warning: 'bg-warning text-warning-foreground border-warning',
  info: 'bg-info text-info-foreground border-info',
  default: 'bg-foreground text-background border-foreground',
  primary: 'bg-primary text-primary-foreground border-primary',
  outline: 'border-foreground text-foreground',
}
