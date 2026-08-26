import { useTranslation } from 'react-i18next'
import { dataExportsApi } from '@/api/endpoints/dataExports'
import { queryKeys } from '@/api/queryKeys'
import { DataExportsList } from '@/features/dataExports/components/DataExportsList'
import { PageHeader } from '@/components/layout/PageHeader'

/** Admin bulk — gated on data-export.school at the route level (see AppRouter). */
export function DataExportsPage() {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('settings.schoolExportTitle')} description={t('settings.schoolExportDescription')} />
      <DataExportsList
        queryKey={queryKeys.dataExportsSchool}
        list={dataExportsApi.listSchool}
        request={dataExportsApi.requestSchool}
        requestLabel={t('settings.requestFullExportLabel')}
        emptyLabel={t('settings.noSchoolExportsYet')}
      />
    </div>
  )
}
