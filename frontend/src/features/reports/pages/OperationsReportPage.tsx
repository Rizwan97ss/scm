import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { reportsApi } from '@/api/endpoints/reports'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Skeleton, StatCard } from '@/components/ui'

export function OperationsReportPage() {
  const { t } = useTranslation()
  const { data, isLoading } = useQuery({ queryKey: queryKeys.reportsOperations, queryFn: reportsApi.operations })

  const nothingVisible = !isLoading && !data?.library && !data?.transport && !data?.hostel

  return (
    <div>
      <PageHeader title={t('nav.operations_report')} description={t('reports.operationsReportDescription')} />

      {isLoading && <Skeleton className="h-48 w-full" />}
      {nothingVisible && <p className="text-sm text-muted-foreground">{t('reports.noOperationsDataVisible')}</p>}

      {data?.library && (
        <div className="mb-8">
          <h3 className="mb-3 text-sm font-semibold">{t('nav.library')}</h3>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label={t('reports.totalBooksLabel')} value={data.library.total_books} />
            <StatCard label={t('reports.issuedThisMonthLabel')} value={data.library.issued_this_month} />
            <StatCard label={t('reports.currentlyOverdueLabel')} value={data.library.currently_overdue} />
          </div>
        </div>
      )}

      {data?.transport && (
        <div className="mb-8">
          <h3 className="mb-3 text-sm font-semibold">{t('nav.transport')}</h3>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label={t('reports.activeVehiclesLabel')} value={data.transport.vehicle_count} />
            <StatCard label={t('reports.studentsAssignedLabel')} value={data.transport.students_assigned} />
          </div>
        </div>
      )}

      {data?.hostel && (
        <div>
          <h3 className="mb-3 text-sm font-semibold">{t('nav.hostel')}</h3>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label={t('reports.roomsLabel')} value={data.hostel.room_count} />
            <StatCard label={t('reports.occupiedOverCapacityLabel')} value={`${data.hostel.total_occupied} / ${data.hostel.total_capacity}`} />
            <StatCard label={t('reports.occupancyLabel')} value={data.hostel.occupancy_percentage != null ? `${data.hostel.occupancy_percentage}%` : '—'} />
          </div>
        </div>
      )}
    </div>
  )
}
