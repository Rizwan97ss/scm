import { useTranslation } from 'react-i18next'
import { roomsApi } from '@/api/endpoints/academics'
import { PageHeader } from '@/components/layout/PageHeader'
import { ImportForm } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'

export function RoomImportPage() {
  const { t } = useTranslation()
  return (
    <div>
      <PageHeader title={t('academics.roomImportTitle')} breadcrumbs={[{ label: t('nav.rooms'), to: routePaths.rooms }, { label: t('common.import') }]} />

      <ImportForm
        entityLabel={t('academics.roomImportEntityLabel')}
        templateUrl={roomsApi.importTemplateUrl}
        templateFilename="room-import-template.xlsx"
        description={t('academics.roomImportDescription')}
        onImport={roomsApi.import}
        supportsMode
      />
    </div>
  )
}
