import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Save } from 'lucide-react'
import { examMarksApi, examsApi } from '@/api/endpoints/exams'
import { studentsApi } from '@/api/endpoints/students'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button, Checkbox, Input, Skeleton, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import type { ApiError } from '@/api/client'

interface EntryState {
  marks_obtained: string
  is_absent: boolean
  remarks: string
}

export function MarksEntryPage() {
  const { examId, examSubjectId } = useParams<{ examId: string; examSubjectId: string }>()
  const examSubjectIdNum = Number(examSubjectId)
  const queryClient = useQueryClient()

  const { data: exam } = useQuery({ queryKey: queryKeys.exam(Number(examId)), queryFn: () => examsApi.get(Number(examId)) })
  // The component (what the marks belong to) and its parent group (what
  // subject/section it's under) — components no longer carry subject/
  // section directly, only their group does. See ExamSubjectGroup in types/exam.ts.
  const group = exam?.exam_subject_groups.find((g) => g.components.some((c) => c.id === examSubjectIdNum))
  const examSubject = group?.components.find((c) => c.id === examSubjectIdNum)

  const { data: roster, isLoading: rosterLoading } = useQuery({
    queryKey: queryKeys.students({ 'filter[current_section_id]': group?.section?.id, per_page: 200 }),
    queryFn: () => studentsApi.list({ 'filter[current_section_id]': group!.section!.id, per_page: 200 }),
    enabled: !!group?.section?.id,
  })

  const { data: existingMarks } = useQuery({
    queryKey: queryKeys.examMarks(examSubjectIdNum),
    queryFn: () => examMarksApi.list(examSubjectIdNum),
  })

  const [entries, setEntries] = useState<Record<number, EntryState>>({})

  useEffect(() => {
    if (!roster) return
    const existingByStudent = new Map((existingMarks ?? []).map((m) => [m.student_id, m]))
    const next: Record<number, EntryState> = {}
    for (const student of roster.data) {
      const mark = existingByStudent.get(student.id)
      next[student.id] = {
        marks_obtained: mark?.marks_obtained !== null && mark?.marks_obtained !== undefined ? String(mark.marks_obtained) : '',
        is_absent: mark?.is_absent ?? false,
        remarks: mark?.remarks ?? '',
      }
    }
    setEntries(next)
  }, [roster, existingMarks])

  const saveMutation = useMutation({
    mutationFn: () =>
      examMarksApi.mark(
        examSubjectIdNum,
        Object.entries(entries).map(([studentId, e]) => ({
          student_id: Number(studentId),
          marks_obtained: e.is_absent || e.marks_obtained === '' ? null : Number(e.marks_obtained),
          is_absent: e.is_absent,
          remarks: e.remarks || null,
        }))
      ),
    onSuccess: (records) => {
      toast.success(`${records.length} mark(s) saved.`)
      queryClient.invalidateQueries({ queryKey: queryKeys.examMarks(examSubjectIdNum) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  if (!exam || !examSubject) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  return (
    <div>
      <PageHeader
        title={`Enter Marks — ${group?.subject?.name}`}
        description={`${exam.name} · ${group?.section?.name} · Max marks: ${examSubject.max_marks}`}
        breadcrumbs={[{ label: 'Exams', to: routePaths.exams }, { label: exam.name, to: routePaths.examDetail(exam.id) }, { label: group?.subject?.name ?? '' }]}
      />

      {rosterLoading && (
        <div className="flex flex-col gap-2">
          {Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className="h-12 w-full" />)}
        </div>
      )}

      {!rosterLoading && (roster?.data.length ?? 0) > 0 && (
        <>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Admission #</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Marks</TableHead>
                <TableHead>Absent</TableHead>
                <TableHead>Remarks</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {roster!.data.map((student) => {
                const entry = entries[student.id] ?? { marks_obtained: '', is_absent: false, remarks: '' }
                return (
                  <TableRow key={student.id}>
                    <TableCell className="text-muted-foreground">{student.admission_number}</TableCell>
                    <TableCell className="font-medium">{student.full_name}</TableCell>
                    <TableCell>
                      <Input
                        type="number"
                        min="0"
                        max={examSubject.max_marks}
                        step="0.01"
                        className="h-8 w-24"
                        disabled={entry.is_absent}
                        value={entry.marks_obtained}
                        onChange={(e) => setEntries((prev) => ({ ...prev, [student.id]: { ...entry, marks_obtained: e.target.value } }))}
                      />
                    </TableCell>
                    <TableCell>
                      <Checkbox
                        checked={entry.is_absent}
                        onCheckedChange={(checked) => setEntries((prev) => ({ ...prev, [student.id]: { ...entry, is_absent: checked, marks_obtained: checked ? '' : entry.marks_obtained } }))}
                        aria-label={`Mark ${student.full_name} absent`}
                      />
                    </TableCell>
                    <TableCell>
                      <Input
                        className="h-8 w-48"
                        placeholder="Optional remarks"
                        value={entry.remarks}
                        onChange={(e) => setEntries((prev) => ({ ...prev, [student.id]: { ...entry, remarks: e.target.value } }))}
                      />
                    </TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>

          <div className="mt-4 flex justify-end">
            <Button onClick={() => saveMutation.mutate()} isLoading={saveMutation.isPending}>
              <Save className="h-4 w-4" /> Save Marks
            </Button>
          </div>
        </>
      )}
    </div>
  )
}
