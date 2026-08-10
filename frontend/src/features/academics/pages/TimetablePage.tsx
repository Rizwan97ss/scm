import { PageHeader } from '@/components/layout/PageHeader'
import { Tabs } from '@/components/ui'
import { TimetablePeriodsManager } from '../components/TimetablePeriodsManager'
import { ClassSubjectTeacherManager } from '../components/ClassSubjectTeacherManager'
import { TimetableGridBuilder } from '../components/TimetableGridBuilder'

export function TimetablePage() {
  return (
    <div>
      <PageHeader title="Timetable" description="Configure periods, assign teachers to subjects, and build each section's weekly schedule." />
      <Tabs
        items={[
          { value: 'grid', label: 'Weekly Grid', content: <TimetableGridBuilder /> },
          { value: 'assignments', label: 'Subject Assignments', content: <ClassSubjectTeacherManager /> },
          { value: 'periods', label: 'Periods', content: <TimetablePeriodsManager /> },
        ]}
      />
    </div>
  )
}
