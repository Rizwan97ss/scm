import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { examsApi, termResultsApi } from '@/api/endpoints/exams'
import { termsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { Select, Skeleton, Tabs } from '@/components/ui'
import { ReportCardDisplay } from './ReportCardDisplay'
import { TermResultDisplay } from './TermResultDisplay'
import type { Student } from '@/types/student'

export function StudentExamResultsTab({ student }: { student: Student }) {
  const { t } = useTranslation()
  const { data: exams } = useQuery({ queryKey: queryKeys.exams({ 'filter[academic_year_id]': student.academic_year_id, per_page: 100 }), queryFn: () => examsApi.list({ 'filter[academic_year_id]': student.academic_year_id, per_page: 100 }) })
  const { data: terms } = useQuery({ queryKey: queryKeys.terms({ 'filter[academic_year_id]': student.academic_year_id, per_page: 100 }), queryFn: () => termsApi.list({ 'filter[academic_year_id]': student.academic_year_id, per_page: 100 }) })

  const [examId, setExamId] = useState<number | undefined>(undefined)
  const [termId, setTermId] = useState<number | undefined>(undefined)

  const { data: reportCard, isLoading: reportLoading } = useQuery({
    queryKey: queryKeys.reportCard(examId ?? 0, student.id),
    queryFn: () => examsApi.reportCard(examId!, student.id),
    enabled: !!examId,
  })
  const { data: termResult, isLoading: termLoading } = useQuery({
    queryKey: queryKeys.termResult(termId ?? 0, student.id),
    queryFn: () => termResultsApi.get(termId!, student.id),
    enabled: !!termId,
  })

  return (
    <Tabs
      items={[
        {
          value: 'by-exam',
          label: t('exams.byExam'),
          content: (
            <div className="flex flex-col gap-4">
              <div className="max-w-xs">
                <Select
                  value={examId ? String(examId) : undefined}
                  onValueChange={(v) => setExamId(Number(v))}
                  options={(exams?.data ?? []).map((e) => ({ value: String(e.id), label: e.name }))}
                  placeholder={t('exams.selectExamPlaceholder')}
                />
              </div>
              {reportLoading && <Skeleton className="h-64 w-full" />}
              {!reportLoading && reportCard && <ReportCardDisplay report={reportCard} pdfUrl={examId ? examsApi.reportCardPdfUrl(examId, student.id) : undefined} />}
            </div>
          ),
        },
        {
          value: 'by-term',
          label: t('exams.consolidatedTermResult'),
          content: (
            <div className="flex flex-col gap-4">
              <div className="max-w-xs">
                <Select
                  value={termId ? String(termId) : undefined}
                  onValueChange={(v) => setTermId(Number(v))}
                  options={(terms?.data ?? []).map((term) => ({ value: String(term.id), label: term.name }))}
                  placeholder={t('exams.selectTermPlaceholder')}
                />
              </div>
              {termLoading && <Skeleton className="h-64 w-full" />}
              {!termLoading && termResult && <TermResultDisplay result={termResult} pdfUrl={termId ? termResultsApi.pdfUrl(termId, student.id) : undefined} />}
            </div>
          ),
        },
      ]}
    />
  )
}
