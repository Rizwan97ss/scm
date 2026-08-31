import { useTranslation } from 'react-i18next'
import { usersApi } from '@/api/endpoints/users'
import { PageHeader } from '@/components/layout/PageHeader'
import { ImportForm } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'

export function UserImportPage() {
  const { t } = useTranslation()
  return (
    <div>
      <PageHeader title={t('users.importTitle')} breadcrumbs={[{ label: t('nav.staff_users'), to: routePaths.users }, { label: t('common.import') }]} />

      <ImportForm
        entityLabel={t('users.importEntityLabel')}
        templateUrl={usersApi.importTemplateUrl}
        templateFilename="staff-import-template.xlsx"
        description={
          <>
            {t('users.importDescriptionBeforeCode')}<code>role</code>{t('users.importDescriptionAfterCode')}
          </>
        }
        onImport={usersApi.import}
      />
    </div>
  )
}
