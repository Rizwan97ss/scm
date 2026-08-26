import { useTranslation } from 'react-i18next'
import { dataExportsApi } from '@/api/endpoints/dataExports'
import { queryKeys } from '@/api/queryKeys'
import { DataExportsList } from '../components/DataExportsList'
import { PageHeader } from '@/components/layout/PageHeader'

/** Self-service — no permission gate, every role can export their own data. */
export function MyDataExportPage() {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('settings.myExportTitle')} description={t('settings.myExportDescription')} />
      <DataExportsList
        queryKey={queryKeys.dataExportsSelf}
        list={dataExportsApi.listSelf}
        request={dataExportsApi.requestSelf}
        requestLabel={t('settings.requestExportLabel')}
        emptyLabel={t('settings.noExportsYet')}
      />
    </div>
  )
}
