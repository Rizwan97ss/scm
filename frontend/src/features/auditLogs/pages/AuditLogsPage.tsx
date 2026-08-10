import { useQuery } from '@tanstack/react-query'
import { auditLogsApi, type AuditLogEntry } from '@/api/endpoints/auditLogs'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, DataTable, type DataTableColumn } from '@/components/ui'
import { formatDateTime } from '@/utils/formatDate'

export function AuditLogsPage() {
  const { setPage, queryParams } = usePagination('-created_at')
  const { data, isLoading } = useQuery({ queryKey: queryKeys.auditLogs(queryParams), queryFn: () => auditLogsApi.list(queryParams) })

  const columns: DataTableColumn<AuditLogEntry>[] = [
    { key: 'created_at', header: 'When', render: (row) => formatDateTime(row.created_at) },
    { key: 'causer', header: 'By', render: (row) => row.causer?.full_name ?? 'System' },
    { key: 'event', header: 'Event', render: (row) => (row.event ? <Badge variant="default">{row.event}</Badge> : '—') },
    { key: 'subject_type', header: 'Subject', render: (row) => row.subject_type ?? '—' },
    { key: 'description', header: 'Description', render: (row) => row.description },
  ]

  return (
    <div>
      <PageHeader title="Audit Log" description="A record of sensitive actions performed across your school." />
      <DataTable
        columns={columns}
        data={data?.data}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        meta={data?.meta}
        onPageChange={setPage}
        emptyTitle="No activity recorded yet"
      />
    </div>
  )
}
