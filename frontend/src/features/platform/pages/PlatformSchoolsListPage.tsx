import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
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

export function PlatformSchoolsListPage() {
  const navigate = useNavigate()
  const { setPage, sort, setSort, queryParams } = usePagination('name')

  const { data, isLoading } = useQuery({
    queryKey: queryKeys.platformSchools(queryParams),
    queryFn: () => platformApi.listSchools(queryParams),
  })

  const columns: DataTableColumn<PlatformSchool>[] = [
    { key: 'name', header: 'School', sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'plan', header: 'Plan', render: (row) => row.plan?.name ?? '—' },
    {
      key: 'billing_status',
      header: 'Billing status',
      render: (row) =>
        row.billing_status ? (
          <Badge variant={BILLING_STATUS_VARIANT[row.billing_status] ?? 'default'}>{row.billing_status}</Badge>
        ) : (
          <span className="text-muted-foreground">—</span>
        ),
    },
    {
      key: 'usage',
      header: 'Students',
      render: (row) => `${row.usage.students}${row.usage.max_students ? ` / ${row.usage.max_students}` : ''}`,
    },
    {
      key: 'staff',
      header: 'Staff',
      render: (row) => `${row.usage.staff}${row.usage.max_staff ? ` / ${row.usage.max_staff}` : ''}`,
    },
    {
      key: 'is_active',
      header: 'Status',
      render: (row) => <Badge variant={row.is_active ? 'success' : 'destructive'}>{row.is_active ? 'Active' : 'Suspended'}</Badge>,
    },
  ]

  return (
    <div>
      <PageHeader title="Schools" description="Every tenant on the platform, across all subscriptions." />
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
        emptyTitle="No schools yet"
      />
    </div>
  )
}
