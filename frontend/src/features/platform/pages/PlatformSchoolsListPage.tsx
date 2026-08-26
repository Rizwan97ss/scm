import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { platformApi } from '@/api/endpoints/platform'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, DataTable, type DataTableColumn } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import type { PlatformSchool } from '@/types/platform'

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

export function PlatformSchoolsListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { setPage, sort, setSort, queryParams } = usePagination('name')

  const { data, isLoading } = useQuery({
    queryKey: queryKeys.platformSchools(queryParams),
    queryFn: () => platformApi.listSchools(queryParams),
  })

  const columns: DataTableColumn<PlatformSchool>[] = [
    { key: 'name', header: t('platform.schoolColumn'), sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'plan', header: t('settings.planLabel'), render: (row) => row.plan?.name ?? '—' },
    {
      key: 'billing_status',
      header: t('platform.billingStatusColumn'),
      render: (row) =>
        row.billing_status ? (
          <Badge variant={BILLING_STATUS_VARIANT[row.billing_status] ?? 'default'}>
            {BILLING_STATUS_LABEL_KEYS[row.billing_status] ? t(BILLING_STATUS_LABEL_KEYS[row.billing_status]) : row.billing_status}
          </Badge>
        ) : (
          <span className="text-muted-foreground">—</span>
        ),
    },
    {
      key: 'usage',
      header: t('nav.students'),
      render: (row) => `${row.usage.students}${row.usage.max_students ? ` / ${row.usage.max_students}` : ''}`,
    },
    {
      key: 'staff',
      header: t('platform.staffColumn'),
      render: (row) => `${row.usage.staff}${row.usage.max_staff ? ` / ${row.usage.max_staff}` : ''}`,
    },
    {
      key: 'is_active',
      header: t('common.status'),
      render: (row) => <Badge variant={row.is_active ? 'success' : 'destructive'}>{row.is_active ? t('common.active') : t('platform.suspendedBadge')}</Badge>,
    },
  ]

  return (
    <div>
      <PageHeader title={t('platform.navSchools')} description={t('platform.schoolsPageDescription')} />
      <DataTable
        columns={columns}
        data={data?.data}
        rowKey={(r) => r.id}
        isLoading={isLoading}
        meta={data?.meta}
        onPageChange={setPage}
        sort={sort}
        onSortChange={setSort}
        onRowClick={(row) => navigate(routePaths.platformSchoolDetail(row.id))}
        emptyTitle={t('platform.noSchoolsYet')}
      />
    </div>
  )
}
