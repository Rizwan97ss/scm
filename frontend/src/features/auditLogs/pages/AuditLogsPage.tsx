import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { auditLogsApi, type AuditLogEntry } from '@/api/endpoints/auditLogs'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, DataTable, type DataTableColumn } from '@/components/ui'
import { formatDateTime } from '@/utils/formatDate'

export function AuditLogsPage() {
  const { t } = useTranslation()
  const { setPage, queryParams } = usePagination('-created_at')
  const { data, isLoading, isError, refetch } = useQuery({ queryKey: queryKeys.auditLogs(queryParams), queryFn: () => auditLogsApi.list(queryParams) })

  const columns: DataTableColumn<AuditLogEntry>[] = [
    { key: 'created_at', header: t('auditLogs.whenColumn'), render: (row) => formatDateTime(row.created_at) },
    { key: 'causer', header: t('auditLogs.byColumn'), render: (row) => row.causer?.full_name ?? t('auditLogs.systemFallback') },
    { key: 'event', header: t('auditLogs.eventColumn'), render: (row) => (row.event ? <Badge variant="default">{row.event}</Badge> : '—') },
    { key: 'subject_type', header: t('auditLogs.subjectColumn'), render: (row) => row.subject_type ?? '—' },
    { key: 'description', header: t('common.description'), render: (row) => row.description },
  ]

  return (
    <div>
      <PageHeader title={t('nav.audit_log')} description={t('auditLogs.description')} />
      <DataTable
        columns={columns}
        data={data?.data}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        isError={isError}
        onRetry={refetch}
        meta={data?.meta}
        onPageChange={setPage}
        emptyTitle={t('auditLogs.noActivityYet')}
        emptyDescription={t('auditLogs.emptyDescription')}
      />
    </div>
  )
}
