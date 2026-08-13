import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
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
          label: 'By Exam',
          content: (
            <div className="flex flex-col gap-4">
              <div className="max-w-xs">
                <Select
                  value={examId ? String(examId) : undefined}
                  onValueChange={(v) => setExamId(Number(v))}
                  options={(exams ?? []).map((e) => ({ value: String(e.id), label: e.name }))}
                  placeholder={exams?.length ? 'Select an exam…' : 'No published exams yet'}
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
          label: 'Consolidated Term Result',
          content: (
            <div className="flex flex-col gap-4">
              <div className="max-w-xs">
                <Select
                  value={termId ? String(termId) : undefined}
                  onValueChange={(v) => setTermId(Number(v))}
                  options={(terms?.data ?? []).map((t) => ({ value: String(t.id), label: t.name }))}
                  placeholder="Select a term…"
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
