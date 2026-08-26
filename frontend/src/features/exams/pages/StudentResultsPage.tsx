import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { CalendarClock } from 'lucide-react'
import { useAuth } from '@/context/AuthContext'
import { studentsApi } from '@/api/endpoints/students'
import { examsApi } from '@/api/endpoints/exams'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Card, CardContent, CardHeader, CardTitle, EmptyState, Skeleton } from '@/components/ui'
import { StudentExamResultsTab } from '../components/StudentExamResultsTab'

/**
 * The Student-role landing page for "my exams and results" — reuses
 * StudentExamResultsTab (the same report-card/term-result view already
 * built for staff looking at one student's profile), just resolved to the
 * logged-in Student's own record via `auth.student_id` rather than a
 * route param. A logged-in Student otherwise has no click-path to any
 * results view at all (see STUDENT_NAV_GROUPS).
 */
export function StudentResultsPage() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const studentId = user?.student_id ?? null

  const { data: student, isLoading: studentLoading } = useQuery({
    queryKey: queryKeys.student(studentId ?? 0),
    queryFn: () => studentsApi.get(studentId!),
    enabled: !!studentId,
  })

  const { data: exams, isLoading: examsLoading } = useQuery({
    queryKey: queryKeys.exams({ 'filter[academic_year_id]': student?.academic_year_id, per_page: 100 }),
    queryFn: () => examsApi.list({ 'filter[academic_year_id]': student!.academic_year_id, per_page: 100 }),
    enabled: !!student,
  })

  if (!studentId) {
    return (
      <div>
        <PageHeader title={t('nav.my_results')} />
        <EmptyState title={t('exams.noStudentRecordTitle')} description={t('exams.noStudentRecordDescription')} />
      </div>
    )
  }

  if (studentLoading || !student) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-40 w-full" />
      </div>
    )
  }

  const upcomingExams = (exams?.data ?? []).filter((exam) =>
    exam.exam_subject_groups.some((group) => group.section?.id === student.current_section_id)
  )

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('nav.my_results')} description={t('exams.myResultsDescription')} />

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <CalendarClock className="h-4 w-4" /> {t('exams.examSchedule')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {examsLoading && <Skeleton className="h-24 w-full" />}
          {!examsLoading && upcomingExams.length === 0 && <p className="text-sm text-muted-foreground">{t('exams.noExamsScheduledYet')}</p>}
          {!examsLoading && upcomingExams.length > 0 && (
            <ul className="flex flex-col divide-y divide-border">
              {upcomingExams.map((exam) => {
                const components = exam.exam_subject_groups
                  .filter((group) => group.section?.id === student.current_section_id)
                  .flatMap((group) => group.components.map((component) => ({ ...component, subjectName: group.subject?.name })))
                  .sort((a, b) => (a.exam_date ?? '').localeCompare(b.exam_date ?? ''))

                return (
                  <li key={exam.id} className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0">
                    <div className="flex items-center gap-2">
                      <span className="font-medium">{exam.name}</span>
                      {exam.is_published && <Badge variant="success">{t('exams.resultsDeclared')}</Badge>}
                    </div>
                    <ul className="flex flex-col gap-1 text-sm text-muted-foreground">
                      {components.map((component) => (
                        <li key={component.id} className="flex items-center justify-between gap-4">
                          <span>
                            {component.subjectName} — {component.assessment_component_type?.name ?? t('exams.component')}
                          </span>
                          <span>{component.exam_date ? new Date(component.exam_date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : t('exams.dateTBA')}</span>
                        </li>
                      ))}
                    </ul>
                  </li>
                )
              })}
            </ul>
          )}
        </CardContent>
      </Card>

      <div>
        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">{t('exams.declaredResults')}</h2>
        <StudentExamResultsTab student={student} />
      </div>
    </div>
  )
}
