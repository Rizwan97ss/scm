import { useTranslation } from 'react-i18next'
import { studentsApi } from '@/api/endpoints/students'
import { PageHeader } from '@/components/layout/PageHeader'
import { ImportForm } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'

export function StudentImportPage() {
  const { t } = useTranslation()
  return (
    <div>
      <PageHeader title={t('students.importPageTitle')} breadcrumbs={[{ label: t('nav.students'), to: routePaths.students }, { label: t('students.importBreadcrumb') }]} />

      <ImportForm
        entityLabel={t('entities.student')}
        templateUrl={studentsApi.importTemplateUrl}
        templateFilename="student-import-template.xlsx"
        description={t('students.importInstructions')}
        onImport={studentsApi.import}
      />
    </div>
  )
}
