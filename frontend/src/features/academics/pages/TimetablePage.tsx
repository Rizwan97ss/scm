import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/layout/PageHeader'
import { Tabs } from '@/components/ui'
import { TimetablePeriodsManager } from '../components/TimetablePeriodsManager'
import { ClassSubjectTeacherManager } from '../components/ClassSubjectTeacherManager'
import { TimetableGridBuilder } from '../components/TimetableGridBuilder'

export function TimetablePage() {
  const { t } = useTranslation()
  return (
    <div>
      <PageHeader title={t('nav.timetable')} description={t('academics.timetableDescription')} />
      <Tabs
        items={[
          { value: 'grid', label: t('academics.weeklyGrid'), content: <TimetableGridBuilder /> },
          { value: 'assignments', label: t('academics.subjectAssignments'), content: <ClassSubjectTeacherManager /> },
          { value: 'periods', label: t('academics.periods'), content: <TimetablePeriodsManager /> },
        ]}
      />
    </div>
  )
}
