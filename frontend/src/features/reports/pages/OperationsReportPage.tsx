import { useQuery } from '@tanstack/react-query'
import { reportsApi } from '@/api/endpoints/reports'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Skeleton, StatCard } from '@/components/ui'

export function OperationsReportPage() {
  const { data, isLoading } = useQuery({ queryKey: queryKeys.reportsOperations, queryFn: reportsApi.operations })

  const nothingVisible = !isLoading && !data?.library && !data?.transport && !data?.hostel

  return (
    <div>
      <PageHeader title="Operations Report" description="Library, transport, and hostel at a glance." />

      {isLoading && <Skeleton className="h-48 w-full" />}
      {nothingVisible && <p className="text-sm text-muted-foreground">No operational data visible to your role.</p>}

      {data?.library && (
        <div className="mb-8">
          <h3 className="mb-3 text-sm font-semibold">Library</h3>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Books" value={data.library.total_books} />
            <StatCard label="Issued This Month" value={data.library.issued_this_month} />
            <StatCard label="Currently Overdue" value={data.library.currently_overdue} />
          </div>
        </div>
      )}

      {data?.transport && (
        <div className="mb-8">
          <h3 className="mb-3 text-sm font-semibold">Transport</h3>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Active Vehicles" value={data.transport.vehicle_count} />
            <StatCard label="Students Assigned" value={data.transport.students_assigned} />
          </div>
        </div>
      )}

      {data?.hostel && (
        <div>
          <h3 className="mb-3 text-sm font-semibold">Hostel</h3>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Rooms" value={data.hostel.room_count} />
            <StatCard label="Occupied / Capacity" value={`${data.hostel.total_occupied} / ${data.hostel.total_capacity}`} />
            <StatCard label="Occupancy" value={data.hostel.occupancy_percentage != null ? `${data.hostel.occupancy_percentage}%` : '—'} />
          </div>
        </div>
      )}
    </div>
  )
}
