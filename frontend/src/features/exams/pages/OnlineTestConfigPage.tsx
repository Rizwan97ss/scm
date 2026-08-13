import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Save } from 'lucide-react'
import { examsApi, onlineTestsApi, questionsApi } from '@/api/endpoints/exams'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Checkbox, Input, Skeleton, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import { QUESTION_TYPE_LABELS } from '@/types/exam'
import type { ApiError } from '@/api/client'

export function OnlineTestConfigPage() {
  const { examId, examSubjectId } = useParams<{ examId: string; examSubjectId: string }>()
  const examSubjectIdNum = Number(examSubjectId)

  const { data: exam } = useQuery({ queryKey: queryKeys.exam(Number(examId)), queryFn: () => examsApi.get(Number(examId)) })
  // The component (what the test is configured on) and its parent group
  // (what subject/section it's under) — see ExamSubjectGroup in types/exam.ts.
  const group = exam?.exam_subject_groups.find((g) => g.components.some((c) => c.id === examSubjectIdNum))
  const examSubject = group?.components.find((c) => c.id === examSubjectIdNum)

  const { data: questions, isLoading } = useQuery({ queryKey: queryKeys.questions({ per_page: 200 }), queryFn: () => questionsApi.list({ per_page: 200 }) })

  // Starts blank rather than pre-loading a previously configured set — there's
  // no GET endpoint for a test's current question list (only the sanitized
  // student-facing TestQuestionResource), and re-saving always replaces the
  // set wholesale (see syncQuestions on the backend), so re-selecting is the
  // simplest correct behavior for now.
  const [selected, setSelected] = useState<Record<number, number | ''>>({})

  const saveMutation = useMutation({
    mutationFn: () =>
      onlineTestsApi.syncQuestions(examSubjectIdNum, {
        questions: Object.entries(selected)
          .filter(([, marks]) => marks !== undefined)
          .map(([questionId, marks]) => ({ question_id: Number(questionId), marks: marks === '' ? null : Number(marks) })),
      }),
    onSuccess: () => toast.success('Online test questions saved.'),
    onError: (error) => toast.error((error as ApiError).message),
  })

  function toggle(questionId: number, defaultMarks: number) {
    setSelected((prev) => {
      const next = { ...prev }
      if (questionId in next) delete next[questionId]
      else next[questionId] = defaultMarks
      return next
    })
  }

  if (!exam || !examSubject) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  const selectedCount = Object.keys(selected).length

  return (
    <div>
      <PageHeader
        title={`Configure Online Test — ${group?.subject?.name}`}
        description={`${exam.name} · ${group?.section?.name} · Select the questions this test will draw from.`}
        breadcrumbs={[{ label: 'Exams', to: routePaths.exams }, { label: exam.name, to: routePaths.examDetail(exam.id) }, { label: group?.subject?.name ?? '' }]}
        actions={
          <Button onClick={() => saveMutation.mutate()} isLoading={saveMutation.isPending} disabled={selectedCount === 0}>
            <Save className="h-4 w-4" /> Save ({selectedCount} selected)
          </Button>
        }
      />

      {isLoading && <Skeleton className="h-64 w-full" />}

      {!isLoading && (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead></TableHead>
              <TableHead>Question</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Marks</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {(questions?.data ?? []).map((question) => (
              <TableRow key={question.id}>
                <TableCell>
                  <Checkbox checked={question.id in selected} onCheckedChange={() => toggle(question.id, question.default_marks)} aria-label={`Select question`} />
                </TableCell>
                <TableCell className="max-w-md">
                  <span className="line-clamp-2">{question.text}</span>
                  {question.subject && <Badge variant="outline" className="ml-2">{question.subject.name}</Badge>}
                </TableCell>
                <TableCell>{QUESTION_TYPE_LABELS[question.type]}</TableCell>
                <TableCell>
                  {question.id in selected ? (
                    <Input
                      type="number"
                      step="0.01"
                      min="0.01"
                      className="h-8 w-20"
                      value={selected[question.id] ?? ''}
                      onChange={(e) => setSelected((prev) => ({ ...prev, [question.id]: e.target.value === '' ? '' : Number(e.target.value) }))}
                    />
                  ) : (
                    <span className="text-muted-foreground">{question.default_marks}</span>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
  )
}
