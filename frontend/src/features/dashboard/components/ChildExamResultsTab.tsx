import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { parentPortalApi } from '@/api/endpoints/dashboard'
import { termsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { Select, Skeleton, Tabs } from '@/components/ui'
import { ReportCardDisplay } from '@/features/exams/components/ReportCardDisplay'
import { TermResultDisplay } from '@/features/exams/components/TermResultDisplay'
import type { Student } from '@/types/student'

/**
 * Uses the dedicated /parent/children/{id}/exams + report-card + term-result
 * endpoints (never the staff-facing /exams/*) — same rationale as
 * ChildAttendanceTab: parent portal routes must keep working even if a
 * School Admin edits the Parent role's exams.view/exam-marks.view
 * permissions. Terms themselves are listed via the shared /terms endpoint
 * (TermPolicy gates on academic-years.view, which Parent already holds).
 */
export function ChildExamResultsTab({ student }: { student: Student }) {
  const { t } = useTranslation()
  const { data: exams } = useQuery({ queryKey: queryKeys.parentChildExams(student.id), queryFn: () => parentPortalApi.childExams(student.id) })
  const { data: terms } = useQuery({ queryKey: queryKeys.terms({ 'filter[academic_year_id]': student.academic_year_id, per_page: 100 }), queryFn: () => termsApi.list({ 'filter[academic_year_id]': student.academic_year_id, per_page: 100 }) })

  const [examId, setExamId] = useState<number | undefined>(undefined)
  const [termId, setTermId] = useState<number | undefined>(undefined)

  const { data: reportCard, isLoading: reportLoading } = useQuery({
    queryKey: queryKeys.parentChildReportCard(student.id, examId ?? 0),
    queryFn: () => parentPortalApi.childReportCard(student.id, examId!),
    enabled: !!examId,
  })
  const { data: termResult, isLoading: termLoading } = useQuery({
    queryKey: queryKeys.parentChildTermResult(student.id, termId ?? 0),
    queryFn: () => parentPortalApi.childTermResult(student.id, termId!),
    enabled: !!termId,
  })

  return (
    <Tabs
      items={[
        {
          value: 'by-exam',
          label: t('students.byExamTab'),
          content: (
            <div className="flex flex-col gap-4">
              <div className="max-w-xs">
                <Select
                  value={examId ? String(examId) : undefined}
                  onValueChange={(v) => setExamId(Number(v))}
                  options={(exams ?? []).map((e) => ({ value: String(e.id), label: e.name }))}
                  placeholder={exams?.length ? t('students.selectExamPlaceholder') : t('students.noPublishedExamsYetShort')}
                  disabled={!exams?.length}
                />
              </div>
              {reportLoading && <Skeleton className="h-64 w-full" />}
              {!reportLoading && reportCard && <ReportCardDisplay report={reportCard} pdfUrl={examId ? parentPortalApi.childReportCardPdfUrl(student.id, examId) : undefined} />}
            </div>
          ),
        },
        {
          value: 'by-term',
          label: t('students.consolidatedTermResultTab'),
          content: (
            <div className="flex flex-col gap-4">
              <div className="max-w-xs">
                <Select
                  value={termId ? String(termId) : undefined}
                  onValueChange={(v) => setTermId(Number(v))}
                  options={(terms?.data ?? []).map((term) => ({ value: String(term.id), label: term.name }))}
                  placeholder={t('students.selectTermPlaceholder')}
                />
              </div>
              {termLoading && <Skeleton className="h-64 w-full" />}
              {!termLoading && termResult && <TermResultDisplay result={termResult} pdfUrl={termId ? parentPortalApi.childTermResultPdfUrl(student.id, termId) : undefined} />}
            </div>
          ),
        },
      ]}
    />
  )
}
