import { dataExportsApi } from '@/api/endpoints/dataExports'
import { queryKeys } from '@/api/queryKeys'
import { DataExportsList } from '../components/DataExportsList'
import { PageHeader } from '@/components/layout/PageHeader'

/** Self-service — no permission gate, every role can export their own data. */
export function MyDataExportPage() {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Export my data" description="Download a copy of the data this platform holds about you." />
      <DataExportsList
        queryKey={queryKeys.dataExportsSelf}
        list={dataExportsApi.listSelf}
        request={dataExportsApi.requestSelf}
        requestLabel="Request an export"
        emptyLabel="No exports requested yet"
      />
    </div>
  )
}
