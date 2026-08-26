import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { parentPortalApi } from '@/api/endpoints/dashboard'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Avatar, Badge, Skeleton, Tabs } from '@/components/ui'
import { GENDER_LABEL_KEYS, STUDENT_STATUS_LABEL_KEYS } from '@/types/enums'
import { formatDate } from '@/utils/formatDate'
import { routePaths } from '@/routes/routePaths'
import { ChildAttendanceTab } from '../components/ChildAttendanceTab'
import { ChildExamResultsTab } from '../components/ChildExamResultsTab'
import { ChildHomeworkTab } from '../components/ChildHomeworkTab'
import { ChildRemarksTab } from '../components/ChildRemarksTab'
import { ChildFeesTab } from '../components/ChildFeesTab'

export function ParentChildProfilePage() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const studentId = Number(id)
  const { data: student, isLoading } = useQuery({
    queryKey: queryKeys.parentChildProfile(studentId),
    queryFn: () => parentPortalApi.childProfile(studentId),
  })

  if (isLoading || !student) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-32 w-full" />
      </div>
    )
  }

  return (
    <div>
      <PageHeader title={student.full_name} breadcrumbs={[{ label: t('nav.my_children'), to: routePaths.parentChildren }, { label: student.full_name }]} />

      <div className="mb-6 flex flex-col gap-4 rounded-lg border border-border bg-card p-4 sm:flex-row sm:items-center sm:gap-6">
        <Avatar name={student.full_name} src={student.photo_url} size={64} />
        <div className="grid flex-1 grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
          <div>
            <p className="text-muted-foreground">{t('students.admissionNumber')}</p>
            <p className="font-medium">{student.admission_number}</p>
          </div>
          <div>
            <p className="text-muted-foreground">{t('common.status')}</p>
            <Badge variant={student.status === 'active' ? 'success' : 'default'}>{t(STUDENT_STATUS_LABEL_KEYS[student.status])}</Badge>
          </div>
          <div>
            <p className="text-muted-foreground">{t('students.gradeSectionLabel')}</p>
            <p className="font-medium">
              {student.grade_level?.name ?? '—'} {student.section ? `- ${student.section.name}` : ''}
            </p>
          </div>
          <div>
            <p className="text-muted-foreground">{t('students.dateOfBirthLabel')}</p>
            <p className="font-medium">
              {formatDate(student.date_of_birth)} ({t(GENDER_LABEL_KEYS[student.gender])})
            </p>
          </div>
        </div>
      </div>

      <Tabs
        items={[
          { value: 'attendance', label: t('nav.attendance'), content: <ChildAttendanceTab studentId={studentId} /> },
          { value: 'exams', label: t('nav.exams'), content: <ChildExamResultsTab student={student} /> },
          { value: 'homework', label: t('nav.homework'), content: <ChildHomeworkTab studentId={studentId} /> },
          { value: 'remarks', label: t('students.remarksTab'), content: <ChildRemarksTab studentId={studentId} /> },
          { value: 'fees', label: t('students.feesTab'), content: <ChildFeesTab studentId={studentId} /> },
        ]}
      />
    </div>
  )
}
