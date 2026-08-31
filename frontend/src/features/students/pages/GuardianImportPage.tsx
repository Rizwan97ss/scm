import { useTranslation } from 'react-i18next'
import { guardiansApi } from '@/api/endpoints/guardians'
import { PageHeader } from '@/components/layout/PageHeader'
import { ImportForm } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'

export function GuardianImportPage() {
  const { t } = useTranslation()
  return (
    <div>
      <PageHeader title={t('students.guardianImportTitle')} breadcrumbs={[{ label: t('nav.guardians'), to: routePaths.guardians }, { label: t('common.import') }]} />

      <ImportForm
        entityLabel={t('students.guardianImportEntityLabel')}
        templateUrl={guardiansApi.importTemplateUrl}
        templateFilename="guardian-import-template.xlsx"
        description={t('students.guardianImportDescription')}
        onImport={guardiansApi.import}
        supportsMode
      />
    </div>
  )
}
