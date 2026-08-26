import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/layout/PageHeader'
import { Tabs } from '@/components/ui'
import { AssessmentComponentTypesPage } from './AssessmentComponentTypesPage'
import { ExamTypesPage } from './ExamTypesPage'

/** One combined settings page rather than two separate nav items — both are small, infrequently-changed lookup tables in the same "how exams are structured" concern. */
export function ExamConfigurationPage() {
  const { t } = useTranslation()
  return (
    <div>
      <PageHeader title={t('nav.exam_configuration')} description={t('exams.examConfigurationDescription')} />
      <Tabs
        items={[
          { value: 'exam-types', label: t('exams.examTypes'), content: <ExamTypesPage /> },
          { value: 'component-types', label: t('exams.componentTypes'), content: <AssessmentComponentTypesPage /> },
        ]}
      />
    </div>
  )
}
