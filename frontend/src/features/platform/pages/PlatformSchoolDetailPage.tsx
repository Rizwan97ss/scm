import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { platformApi } from '@/api/endpoints/platform'
import { plansApi } from '@/api/endpoints/plans'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Card, CardContent, FormField, Input, Modal, Select, Skeleton, StatCard } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import { formatDate } from '@/utils/formatDate'
import type { ApiError } from '@/api/client'

export function PlatformSchoolDetailPage() {
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
      toast.success('Plan updated.')
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
        toast.success('School permanently deleted.')
        navigate(routePaths.platformSchools, { replace: true })
        return
      }
      toast.success('School anonymized and deactivated.')
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
        breadcrumbs={[{ label: 'Schools', to: routePaths.platformSchools }, { label: school.name }]}
        actions={<Badge variant={school.is_active ? 'success' : 'destructive'}>{school.is_active ? 'Active' : 'Suspended'}</Badge>}
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard label="Plan" value={school.plan?.name ?? '—'} />
        <StatCard label="Billing status" value={school.billing_status ?? '—'} />
        <StatCard label="Trial ends" value={school.trial_ends_at ? formatDate(school.trial_ends_at) : '—'} />
      </div>

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Card>
          <CardContent className="flex flex-col gap-1 pt-6">
            <span className="text-sm text-muted-foreground">Students</span>
            <span className="text-lg font-semibold">
              {school.usage.students}
              {school.usage.max_students ? ` / ${school.usage.max_students}` : ' (unlimited)'}
            </span>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="flex flex-col gap-1 pt-6">
            <span className="text-sm text-muted-foreground">Staff</span>
            <span className="text-lg font-semibold">
              {school.usage.staff}
              {school.usage.max_staff ? ` / ${school.usage.max_staff}` : ' (unlimited)'}
            </span>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardContent className="flex flex-col gap-4 pt-6">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Details</h2>
          <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-muted-foreground">Short name</dt>
              <dd>{school.short_name}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Email</dt>
              <dd>{school.email ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Stripe customer</dt>
              <dd>{school.stripe_id ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Created</dt>
              <dd>{formatDate(school.created_at)}</dd>
            </div>
          </dl>

          <div className="border-t border-border pt-4">
            <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Change plan</h2>
            <div className="flex items-center gap-2">
              <div className="w-64">
                <Select
                  value={selectedPlanId}
                  onValueChange={setSelectedPlanId}
                  options={(plans ?? []).filter((p) => p.id !== school.plan?.id).map((p) => ({ value: String(p.id), label: p.name }))}
                  placeholder="Select a plan…"
                />
              </div>
              <Button
                disabled={!selectedPlanId}
                isLoading={changePlanMutation.isPending}
                onClick={() => selectedPlanId && changePlanMutation.mutate(Number(selectedPlanId))}
              >
                Update
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card className="mt-6 border-destructive/30">
        <CardContent className="flex flex-col gap-4 pt-6">
          <div>
            <h2 className="text-sm font-semibold uppercase tracking-wide text-destructive">Danger zone</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Offboard this school when their subscription has ended. Anonymize scrubs every account's PII but keeps
              financial/academic records queryable; delete permanently drops the tenant's entire database.
            </p>
          </div>
          <Button
            variant="destructive"
            className="self-start"
            onClick={() => {
              setOffboardMode('anonymize')
              setOffboardOpen(true)
            }}
          >
            Offboard school…
          </Button>
        </CardContent>
      </Card>

      <Modal
        open={offboardOpen}
        onOpenChange={(open) => {
          setOffboardOpen(open)
          if (!open) setConfirmName('')
        }}
        title={`Offboard ${school.name}`}
        description="This action cannot be undone."
        footer={
          <>
            <Button variant="outline" onClick={() => setOffboardOpen(false)} disabled={offboardMutation.isPending}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              isLoading={offboardMutation.isPending}
              disabled={confirmName !== school.name}
              onClick={() => offboardMutation.mutate()}
            >
              {offboardMode === 'delete' ? 'Permanently delete' : 'Anonymize & deactivate'}
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
                <strong>Anonymize</strong> — scrub PII, deactivate, keep the database (recommended)
              </span>
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="radio" name="offboard-mode" checked={offboardMode === 'delete'} onChange={() => setOffboardMode('delete')} />
              <span>
                <strong>Permanently delete</strong> — physically drops the tenant database
              </span>
            </label>
          </div>

          <FormField label={`Type "${school.name}" to confirm`} htmlFor="confirm-school-name">
            <Input id="confirm-school-name" value={confirmName} onChange={(e) => setConfirmName(e.target.value)} autoComplete="off" />
          </FormField>
        </div>
      </Modal>
    </div>
  )
}
