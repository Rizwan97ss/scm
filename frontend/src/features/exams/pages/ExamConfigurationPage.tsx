import { PageHeader } from '@/components/layout/PageHeader'
import { Tabs } from '@/components/ui'
import { AssessmentComponentTypesPage } from './AssessmentComponentTypesPage'
import { ExamTypesPage } from './ExamTypesPage'

/** One combined settings page rather than two separate nav items — both are small, infrequently-changed lookup tables in the same "how exams are structured" concern. */
export function ExamConfigurationPage() {
  return (
    <div>
      <PageHeader title="Exam Configuration" description="Exam types (Class Test, Trimester, ...) and assessment components (Online MCQ, Written, ...) — both configurable per school." />
      <Tabs
        items={[
          { value: 'exam-types', label: 'Exam Types', content: <ExamTypesPage /> },
          { value: 'component-types', label: 'Component Types', content: <AssessmentComponentTypesPage /> },
        ]}
      />
    </div>
  )
}
