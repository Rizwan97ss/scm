import { useTranslation } from 'react-i18next'
import { subjectsApi } from '@/api/endpoints/academics'
import { PageHeader } from '@/components/layout/PageHeader'
import { ImportForm } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'

export function SubjectImportPage() {
  const { t } = useTranslation()
  return (
    <div>
      <PageHeader title={t('academics.subjectImportTitle')} breadcrumbs={[{ label: t('nav.subjects'), to: routePaths.subjects }, { label: t('common.import') }]} />

      <ImportForm
        entityLabel={t('academics.subjectImportEntityLabel')}
        templateUrl={subjectsApi.importTemplateUrl}
        templateFilename="subject-import-template.xlsx"
        description={t('academics.subjectImportDescription')}
        onImport={subjectsApi.import}
        supportsMode
      />
    </div>
  )
}
