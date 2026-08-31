import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { importLogsApi } from '@/api/endpoints/importLogs'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, ConfirmDialog, DataTable, type DataTableColumn } from '@/components/ui'
import { formatApiError, type ApiError } from '@/api/client'
import { formatDateTime } from '@/utils/formatDate'
import type { ImportLog, ImportUndoResult } from '@/types/import'

export function ImportLogsPage() {
  const { setPage, queryParams } = usePagination('-created_at')
  const { can } = usePermission()
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [undoTarget, setUndoTarget] = useState<ImportLog | null>(null)

  const { data, isLoading, isError, refetch } = useQuery({ queryKey: queryKeys.importLogs(queryParams), queryFn: () => importLogsApi.list(queryParams) })

  const undoMutation = useMutation({
    mutationFn: (id: number) => importLogsApi.undo(id),
    onSuccess: (result: ImportUndoResult) => {
      setUndoTarget(null)
      queryClient.invalidateQueries({ queryKey: ['import-logs'] })
      if (result.blocked.length > 0) {
        const blockedList = result.blocked.map((b) => t('imports.logsUndoBlockedItem', { type: b.type, label: b.label })).join(', ')
        toast.warning(t('imports.logsUndoWarning', { deleted: result.deleted, blockedCount: result.blocked.length, blockedList }))
      } else {
        toast.success(t('imports.logsUndoSuccess', { count: result.deleted }))
      }
    },
    onError: (error) => {
      setUndoTarget(null)
      toast.error(formatApiError(error as ApiError))
    },
  })

  const columns: DataTableColumn<ImportLog>[] = [
    { key: 'created_at', header: t('imports.logsColumnWhen'), render: (row) => formatDateTime(row.created_at) },
    { key: 'entity', header: t('imports.logsColumnEntity'), render: (row) => <Badge variant="default">{row.entity}</Badge> },
    { key: 'performed_by', header: t('imports.logsColumnBy'), render: (row) => row.performed_by?.full_name ?? t('imports.logsUnknownPerformer') },
    { key: 'file_name', header: t('imports.logsColumnFile'), render: (row) => row.file_name },
    { key: 'mode', header: t('imports.logsColumnMode'), render: (row) => row.mode },
    {
      key: 'result',
      header: t('imports.logsColumnResult'),
      render: (row) => (
        <div className="flex flex-wrap gap-1">
          {row.dry_run && <Badge variant="outline">{t('imports.logsPreviewOnly')}</Badge>}
          {row.created_count > 0 && <Badge variant="success">{t('imports.logsCreatedCount', { count: row.created_count })}</Badge>}
          {row.updated_count > 0 && <Badge variant="info">{t('imports.logsUpdatedCount', { count: row.updated_count })}</Badge>}
          {row.failed_count > 0 && <Badge variant="destructive">{t('imports.logsFailedCount', { count: row.failed_count })}</Badge>}
          {row.undone_at && <Badge variant="outline">{t('imports.logsUndoneAt', { date: formatDateTime(row.undone_at) })}</Badge>}
        </div>
      ),
    },
    {
      key: 'actions',
      header: t('imports.logsColumnActions'),
      render: (row) =>
        row.can_undo && can('audit-logs.manage') ? (
          <Button variant="outline" size="sm" onClick={() => setUndoTarget(row)}>
            {t('imports.logsUndo')}
          </Button>
        ) : null,
    },
  ]

  return (
    <div>
      <PageHeader title={t('imports.logsTitle')} description={t('imports.logsDescription')} />
      <DataTable
        columns={columns}
        data={data?.data}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        isError={isError}
        onRetry={refetch}
        meta={data?.meta}
        onPageChange={setPage}
        emptyTitle={t('imports.logsEmptyTitle')}
        emptyDescription={t('imports.logsEmptyDescription')}
      />

      <ConfirmDialog
        open={undoTarget !== null}
        onOpenChange={(open) => !open && setUndoTarget(null)}
        title={t('imports.logsUndoDialogTitle', { entity: undoTarget?.entity })}
        description={t('imports.logsUndoDialogDescription', { count: undoTarget?.created_count, fileName: undoTarget?.file_name })}
        confirmLabel={t('imports.logsUndoConfirmLabel')}
        isLoading={undoMutation.isPending}
        onConfirm={() => undoTarget && undoMutation.mutate(undoTarget.id)}
      />
    </div>
  )
}
