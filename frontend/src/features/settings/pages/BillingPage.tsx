import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { ExternalLink } from 'lucide-react'
import { billingApi } from '@/api/endpoints/billing'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Card, CardContent, Skeleton, StatCard } from '@/components/ui'
import { formatDate } from '@/utils/formatDate'
import type { ApiError } from '@/api/client'

const BILLING_STATUS_VARIANT: Record<string, 'success' | 'warning' | 'destructive' | 'default'> = {
  active: 'success',
  trialing: 'success',
  past_due: 'warning',
  canceled: 'destructive',
  unpaid: 'destructive',
  incomplete_expired: 'destructive',
}

const BILLING_STATUS_LABEL_KEYS: Record<string, string> = {
  active: 'settings.billingStatusActive',
  trialing: 'settings.billingStatusTrialing',
  past_due: 'settings.billingStatusPastDue',
  canceled: 'settings.billingStatusCanceled',
  unpaid: 'settings.billingStatusUnpaid',
  incomplete_expired: 'settings.billingStatusIncompleteExpired',
}

export function BillingPage() {
  const { t } = useTranslation()
  const [redirecting, setRedirecting] = useState(false)
  const { data: school, isLoading } = useQuery({ queryKey: queryKeys.billing, queryFn: billingApi.show })

  const portalMutation = useMutation({
    mutationFn: billingApi.portal,
    onSuccess: (url) => {
      setRedirecting(true)
      window.location.href = url
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  if (isLoading || !school) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  return (
    <div>
      <PageHeader
        title={t('nav.billing')}
        description={t('settings.billingDescription')}
        actions={
          <Button variant="outline" isLoading={portalMutation.isPending || redirecting} onClick={() => portalMutation.mutate()}>
            {t('settings.manageBillingAction')} <ExternalLink className="h-4 w-4" />
          </Button>
        }
      />

      {school.billing_status === 'past_due' && (
        <p className="mb-4 rounded-md bg-warning/10 px-3 py-2 text-sm text-warning">{t('settings.pastDueWarning')}</p>
      )}
      {school.billing_status && ['canceled', 'unpaid', 'incomplete_expired'].includes(school.billing_status) && (
        <p className="mb-4 rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">{t('settings.inactiveWarning')}</p>
      )}

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard label={t('settings.planLabel')} value={school.plan?.name ?? '—'} />
        <StatCard
          label={t('common.status')}
          value={
            school.billing_status ? (
              <Badge variant={BILLING_STATUS_VARIANT[school.billing_status] ?? 'default'}>
                {BILLING_STATUS_LABEL_KEYS[school.billing_status] ? t(BILLING_STATUS_LABEL_KEYS[school.billing_status]) : school.billing_status}
              </Badge>
            ) : (
              '—'
            )
          }
        />
        <StatCard label={t('settings.trialEndsLabel')} value={school.trial_ends_at ? formatDate(school.trial_ends_at) : '—'} />
      </div>

      <Card>
        <CardContent className="grid grid-cols-1 gap-4 pt-6 sm:grid-cols-2">
          <div>
            <span className="text-sm text-muted-foreground">{t('nav.students')}</span>
            <p className="text-lg font-semibold">
              {school.usage.students}
              {school.usage.max_students ? ` / ${school.usage.max_students}` : t('settings.unlimitedSuffix')}
            </p>
          </div>
          <div>
            <span className="text-sm text-muted-foreground">{t('settings.staffUsageLabel')}</span>
            <p className="text-lg font-semibold">
              {school.usage.staff}
              {school.usage.max_staff ? ` / ${school.usage.max_staff}` : t('settings.unlimitedSuffix')}
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
