import { useTranslation } from 'react-i18next'
import { departmentsApi } from '@/api/endpoints/academics'
import { PageHeader } from '@/components/layout/PageHeader'
import { ImportForm } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'

export function DepartmentImportPage() {
  const { t } = useTranslation()
  return (
    <div>
      <PageHeader title={t('academics.departmentImportTitle')} breadcrumbs={[{ label: t('nav.departments'), to: routePaths.departments }, { label: t('common.import') }]} />

      <ImportForm
        entityLabel={t('academics.departmentImportEntityLabel')}
        templateUrl={departmentsApi.importTemplateUrl}
        templateFilename="department-import-template.xlsx"
        description={t('academics.departmentImportDescription')}
        onImport={departmentsApi.import}
        supportsMode
        columns={['name', 'code', 'description']}
      />
    </div>
  )
}
