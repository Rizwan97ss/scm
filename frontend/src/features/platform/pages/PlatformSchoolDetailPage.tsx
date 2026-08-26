import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { platformApi } from '@/api/endpoints/platform'
import { plansApi } from '@/api/endpoints/plans'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Card, CardContent, FormField, Input, Modal, Select, Skeleton, StatCard } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import { formatDate } from '@/utils/formatDate'
import type { ApiError } from '@/api/client'

const BILLING_STATUS_LABEL_KEYS: Record<string, string> = {
  active: 'settings.billingStatusActive',
  trialing: 'settings.billingStatusTrialing',
  past_due: 'settings.billingStatusPastDue',
  canceled: 'settings.billingStatusCanceled',
  unpaid: 'settings.billingStatusUnpaid',
  incomplete_expired: 'settings.billingStatusIncompleteExpired',
}

export function PlatformSchoolDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const schoolId = Number(id)
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [selectedPlanId, setSelectedPlanId] = useState<string | undefined>(undefined)
  const [offboardOpen, setOffboardOpen] = useState(false)
  const [offboardMode, setOffboardMode] = useState<'anonymize' | 'delete'>('anonymize')
  const [confirmName, setConfirmName] = useState('')

  const { data: school, isLoading } = useQuery({
    queryKey: queryKeys.platformSchool(schoolId),
    queryFn: () => platformApi.getSchool(schoolId),
  })
  const { data: plans } = useQuery({ queryKey: queryKeys.plans, queryFn: plansApi.list })

  const changePlanMutation = useMutation({
    mutationFn: (planId: number) => platformApi.changePlan(schoolId, planId),
    onSuccess: () => {
      toast.success(t('platform.planUpdatedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.platformSchool(schoolId) })
      queryClient.invalidateQueries({ queryKey: ['platform', 'schools'] })
      setSelectedPlanId(undefined)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const offboardMutation = useMutation({
    mutationFn: () => platformApi.offboardSchool(schoolId, offboardMode),
    onSuccess: () => {
      setOffboardOpen(false)
      setConfirmName('')
      if (offboardMode === 'delete') {
        toast.success(t('platform.schoolDeletedToast'))
        navigate(routePaths.platformSchools, { replace: true })
        return
      }
      toast.success(t('platform.schoolAnonymizedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.platformSchool(schoolId) })
      queryClient.invalidateQueries({ queryKey: ['platform', 'schools'] })
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
        title={school.name}
        breadcrumbs={[{ label: t('platform.navSchools'), to: routePaths.platformSchools }, { label: school.name }]}
        actions={<Badge variant={school.is_active ? 'success' : 'destructive'}>{school.is_active ? t('common.active') : t('platform.suspendedBadge')}</Badge>}
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard label={t('settings.planLabel')} value={school.plan?.name ?? '—'} tone="primary" />
        <StatCard
          label={t('platform.billingStatusColumn')}
          value={school.billing_status ? (BILLING_STATUS_LABEL_KEYS[school.billing_status] ? t(BILLING_STATUS_LABEL_KEYS[school.billing_status]) : school.billing_status) : '—'}
          tone="info"
        />
        <StatCard label={t('settings.trialEndsLabel')} value={school.trial_ends_at ? formatDate(school.trial_ends_at) : '—'} tone="warning" />
      </div>

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Card>
          <CardContent className="flex flex-col gap-1 pt-6">
            <span className="text-sm text-muted-foreground">{t('nav.students')}</span>
            <span className="text-lg font-semibold">
              {school.usage.students}
              {school.usage.max_students ? ` / ${school.usage.max_students}` : t('settings.unlimitedSuffix')}
            </span>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex flex-col gap-1 pt-6">
            <span className="text-sm text-muted-foreground">{t('platform.staffColumn')}</span>
            <span className="text-lg font-semibold">
              {school.usage.staff}
              {school.usage.max_staff ? ` / ${school.usage.max_staff}` : t('settings.unlimitedSuffix')}
            </span>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardContent className="flex flex-col gap-4 pt-6">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">{t('platform.detailsHeading')}</h2>
          <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-muted-foreground">{t('platform.shortNameLabel')}</dt>
              <dd>{school.short_name}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{t('common.email')}</dt>
              <dd>{school.email ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{t('platform.stripeCustomerLabel')}</dt>
              <dd>{school.stripe_id ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{t('platform.createdLabel')}</dt>
              <dd>{formatDate(school.created_at)}</dd>
            </div>
          </dl>

          <div className="border-t border-border pt-4">
            <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">{t('platform.changePlanHeading')}</h2>
            <div className="flex items-center gap-2">
              <div className="w-64">
                <Select
                  value={selectedPlanId}
                  onValueChange={setSelectedPlanId}
                  options={(plans ?? []).filter((p) => p.id !== school.plan?.id).map((p) => ({ value: String(p.id), label: p.name }))}
                  placeholder={t('platform.selectPlanPlaceholder')}
                />
              </div>
              <Button
                disabled={!selectedPlanId}
                isLoading={changePlanMutation.isPending}
                onClick={() => selectedPlanId && changePlanMutation.mutate(Number(selectedPlanId))}
              >
                {t('platform.updateAction')}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card className="mt-6 border-destructive/30">
        <CardContent className="flex flex-col gap-4 pt-6">
          <div>
            <h2 className="text-sm font-semibold uppercase tracking-wide text-destructive">{t('platform.dangerZoneHeading')}</h2>
            <p className="mt-1 text-sm text-muted-foreground">{t('platform.dangerZoneDescription')}</p>
          </div>
          <Button
            variant="destructive"
            className="self-start"
            onClick={() => {
              setOffboardMode('anonymize')
              setOffboardOpen(true)
            }}
          >
            {t('platform.offboardSchoolAction')}
          </Button>
        </CardContent>
      </Card>

      <Modal
        open={offboardOpen}
        onOpenChange={(open) => {
          setOffboardOpen(open)
          if (!open) setConfirmName('')
        }}
        title={t('platform.offboardModalTitle', { name: school.name })}
        description={t('common.cannotBeUndone')}
        footer={
          <>
            <Button variant="outline" onClick={() => setOffboardOpen(false)} disabled={offboardMutation.isPending}>
              {t('common.cancel')}
            </Button>
            <Button
              variant="destructive"
              isLoading={offboardMutation.isPending}
              disabled={confirmName !== school.name}
              onClick={() => offboardMutation.mutate()}
            >
              {offboardMode === 'delete' ? t('platform.permanentlyDeleteAction') : t('platform.anonymizeDeactivateAction')}
            </Button>
          </>
        }
      >
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="offboard-mode"
                checked={offboardMode === 'anonymize'}
                onChange={() => setOffboardMode('anonymize')}
              />
              <span>
                <strong>{t('platform.anonymizeOptionTitle')}</strong> — {t('platform.anonymizeOptionDescription')}
              </span>
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="radio" name="offboard-mode" checked={offboardMode === 'delete'} onChange={() => setOffboardMode('delete')} />
              <span>
                <strong>{t('platform.deleteOptionTitle')}</strong> — {t('platform.deleteOptionDescription')}
              </span>
            </label>
          </div>

          <FormField label={t('platform.confirmNameLabel', { name: school.name })} htmlFor="confirm-school-name">
            <Input id="confirm-school-name" value={confirmName} onChange={(e) => setConfirmName(e.target.value)} autoComplete="off" />
          </FormField>
        </div>
      </Modal>
    </div>
  )
}
