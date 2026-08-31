import { useTranslation } from 'react-i18next'
import { sectionsApi } from '@/api/endpoints/academics'
import { PageHeader } from '@/components/layout/PageHeader'
import { ImportForm } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'

export function SectionImportPage() {
  const { t } = useTranslation()
  return (
    <div>
      <PageHeader title={t('academics.sectionImportTitle')} breadcrumbs={[{ label: t('nav.sections'), to: routePaths.sections }, { label: t('common.import') }]} />

      <ImportForm
        entityLabel={t('academics.sectionImportEntityLabel')}
        templateUrl={sectionsApi.importTemplateUrl}
        templateFilename="section-import-template.xlsx"
        description={t('academics.sectionImportDescription')}
        onImport={sectionsApi.import}
        supportsMode
      />
    </div>
  )
}
