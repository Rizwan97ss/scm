import { dataExportsApi } from '@/api/endpoints/dataExports'
import { queryKeys } from '@/api/queryKeys'
import { DataExportsList } from '@/features/dataExports/components/DataExportsList'
import { PageHeader } from '@/components/layout/PageHeader'

/** Admin bulk — gated on data-export.school at the route level (see AppRouter). */
export function DataExportsPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="School data export" description="Export every record this school's tenant database holds." />
      <DataExportsList
        queryKey={queryKeys.dataExportsSchool}
        list={dataExportsApi.listSchool}
        request={dataExportsApi.requestSchool}
        requestLabel="Request a full export"
        emptyLabel="No school-wide exports requested yet"
      />
    </div>
  )
}
