import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { CheckCircle2, AlertTriangle, XCircle } from 'lucide-react'
import { fetchSystemHealth } from '@/api/endpoints/settings'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent, Skeleton } from '@/components/ui'
import { cn } from '@/utils/cn'
import type { SystemHealthCheck, SystemHealthStatus } from '@/types/systemHealth'

const STATUS_ICON: Record<SystemHealthStatus, typeof CheckCircle2> = {
  ok: CheckCircle2,
  warning: AlertTriangle,
  error: XCircle,
}

const STATUS_CLASSES: Record<SystemHealthStatus, string> = {
  ok: 'text-success',
  warning: 'text-warning',
  error: 'text-destructive',
}

function CheckRow({ check }: { check: SystemHealthCheck }) {
  const Icon = STATUS_ICON[check.status]

  return (
    <div className="flex items-start gap-3 border-b border-border py-3 last:border-b-0">
      <Icon className={cn('mt-0.5 h-4 w-4 shrink-0', STATUS_CLASSES[check.status])} />
      <div>
        <p className="text-sm font-medium">{check.label}</p>
        <p className="text-sm text-muted-foreground">{check.message}</p>
      </div>
    </div>
  )
}

export function SystemHealthPage() {
  const { t } = useTranslation()
  const { data, isLoading } = useQuery({ queryKey: queryKeys.systemHealth, queryFn: fetchSystemHealth })

  const errors = data?.checks.filter((c) => c.status === 'error') ?? []
  const warnings = data?.checks.filter((c) => c.status === 'warning') ?? []
  const ok = data?.checks.filter((c) => c.status === 'ok') ?? []

  return (
    <div>
      <PageHeader title={t('settings.systemHealthTitle')} description={t('settings.systemHealthDescription')} />

      {isLoading && <Skeleton className="h-64 w-full" />}

      {!isLoading && data && (
        <div className="flex flex-col gap-4">
          <Card>
            <CardContent className="flex items-center gap-4 pt-4 sm:pt-6">
              <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border-4 border-primary/20 text-lg font-semibold">
                {data.completion_percentage}%
              </div>
              <div>
                <p className="font-medium">{t('settings.systemHealthSetupComplete')}</p>
                <p className="text-sm text-muted-foreground">
                  {t('settings.systemHealthChecksPassing', { ok: ok.length, total: data.checks.length })}
                  {errors.length > 0 && t('settings.systemHealthNeedAttention', { count: errors.length })}
                  {warnings.length > 0 && t('settings.systemHealthWorthLook', { count: warnings.length })}.
                </p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-4 sm:pt-6">
              {[...errors, ...warnings, ...ok].map((check) => (
                <CheckRow key={check.key} check={check} />
              ))}
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  )
}
