import { useMutation, useQuery, useQueryClient, type QueryKey } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Badge, Button, Card, CardContent, EmptyState } from '@/components/ui'
import { dataExportsApi } from '@/api/endpoints/dataExports'
import { formatDate } from '@/utils/formatDate'
import type { BadgeVariant } from '@/components/ui/Badge'
import type { DataExport } from '@/types/dataExport'
import type { ApiError } from '@/api/client'

const STATUS_VARIANTS: Record<DataExport['status'], BadgeVariant> = {
  pending: 'default',
  processing: 'info',
  ready: 'success',
  failed: 'destructive',
}

const STATUS_LABEL_KEYS: Record<DataExport['status'], string> = {
  pending: 'settings.exportStatusPending',
  processing: 'settings.exportStatusProcessing',
  ready: 'settings.exportStatusReady',
  failed: 'settings.exportStatusFailed',
}

/**
 * Shared by the self-service ("export my data") and admin bulk ("export
 * the whole school") pages — same request/list/download shape, only which
 * API functions get called differs (see DataExportController's own
 * docblock for why the backend keeps this as one concern too).
 */
export function DataExportsList({
  queryKey,
  list,
  request,
  requestLabel,
  emptyLabel,
}: {
  queryKey: QueryKey
  list: () => Promise<DataExport[]>
  request: () => Promise<DataExport>
  requestLabel: string
  emptyLabel: string
}) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const { data: exports, isLoading } = useQuery({
    queryKey,
    queryFn: list,
    // Polls while anything is still generating — export generation runs in
    // a background queue job, there's no push notification for "it's ready."
    refetchInterval: (query) => (query.state.data?.some((e) => e.status === 'pending' || e.status === 'processing') ? 3000 : false),
  })

  const requestMutation = useMutation({
    mutationFn: request,
    onSuccess: () => {
      toast.success(t('settings.exportStartedToast'))
      queryClient.invalidateQueries({ queryKey })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <div className="flex flex-col gap-4">
      <Button onClick={() => requestMutation.mutate()} isLoading={requestMutation.isPending} className="self-start">
        {requestLabel}
      </Button>

      {isLoading && <p className="text-sm text-muted-foreground">{t('common.loading')}</p>}

      {!isLoading && (exports?.length ?? 0) === 0 && <EmptyState title={emptyLabel} />}

      {(exports ?? []).map((exportItem) => (
        <Card key={exportItem.id}>
          <CardContent className="flex items-center justify-between gap-4 pt-6">
            <div className="flex flex-col gap-1">
              <div className="flex items-center gap-2">
                <Badge variant={STATUS_VARIANTS[exportItem.status]}>{t(STATUS_LABEL_KEYS[exportItem.status])}</Badge>
                <span className="text-sm text-muted-foreground">{t('settings.requestedLabel', { date: formatDate(exportItem.created_at) })}</span>
              </div>
              {exportItem.requested_by && <p className="text-sm text-muted-foreground">{t('settings.requestedByLabel', { name: exportItem.requested_by })}</p>}
              {exportItem.status === 'failed' && exportItem.failure_reason && (
                <p className="text-sm text-destructive">{exportItem.failure_reason}</p>
              )}
              {exportItem.status === 'ready' && exportItem.expires_at && (
                <p className="text-xs text-muted-foreground">{t('settings.availableUntilLabel', { date: formatDate(exportItem.expires_at) })}</p>
              )}
            </div>

            {exportItem.status === 'ready' && (
              <Button variant="outline" onClick={() => window.open(dataExportsApi.downloadUrl(exportItem.id), '_blank')}>
                {t('common.download')}
              </Button>
            )}
          </CardContent>
        </Card>
      ))}
    </div>
  )
}
