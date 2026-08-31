import { useTranslation } from 'react-i18next'
import { gradeLevelsApi } from '@/api/endpoints/academics'
import { PageHeader } from '@/components/layout/PageHeader'
import { ImportForm } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'

export function GradeLevelImportPage() {
  const { t } = useTranslation()
  return (
    <div>
      <PageHeader title={t('academics.gradeLevelImportTitle')} breadcrumbs={[{ label: t('nav.grade_levels'), to: routePaths.gradeLevels }, { label: t('common.import') }]} />

      <ImportForm
        entityLabel={t('academics.gradeLevelImportEntityLabel')}
        templateUrl={gradeLevelsApi.importTemplateUrl}
        templateFilename="grade-level-import-template.xlsx"
        description={t('academics.gradeLevelImportDescription')}
        onImport={gradeLevelsApi.import}
        supportsMode
      />
    </div>
  )
}
