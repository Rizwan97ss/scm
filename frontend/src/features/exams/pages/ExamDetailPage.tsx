import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CheckCircle2, ClipboardList, ListChecks, Plus, XCircle } from 'lucide-react'
import { examsApi } from '@/api/endpoints/exams'
import { sectionsApi, subjectsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Checkbox, FormField, Input, Modal, Select, Skeleton, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import type { ExamSubjectInput } from '@/types/exam'
import type { ApiError } from '@/api/client'

const EMPTY_SUBJECT: ExamSubjectInput = { subject_id: 0, section_id: 0, max_marks: 100, is_online: false, max_attempts: 1, shuffle_questions: true }

export function ExamDetailPage() {
  const { examId } = useParams<{ examId: string }>()
  const id = Number(examId)
  const { can } = usePermission()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data: exam, isLoading } = useQuery({ queryKey: queryKeys.exam(id), queryFn: () => examsApi.get(id) })
  const { data: subjects } = useQuery({ queryKey: queryKeys.subjects({ per_page: 100 }), queryFn: () => subjectsApi.list({ per_page: 100 }) })
  const { data: sections } = useQuery({ queryKey: queryKeys.sections({ per_page: 100 }), queryFn: () => sectionsApi.list({ per_page: 100 }) })

  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<ExamSubjectInput>(EMPTY_SUBJECT)

  const publishMutation = useMutation({
    mutationFn: () => examsApi.publish(id),
    onSuccess: () => {
      toast.success('Exam published — students and parents can now see results.')
      queryClient.invalidateQueries({ queryKey: queryKeys.exam(id) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })
  const unpublishMutation = useMutation({
    mutationFn: () => examsApi.unpublish(id),
    onSuccess: () => {
      toast.success('Exam unpublished.')
      queryClient.invalidateQueries({ queryKey: queryKeys.exam(id) });
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const addSubjectMutation = useMutation({
    mutationFn: () => examsApi.update(id, { academic_year_id: exam!.academic_year_id, term_id: exam!.term_id, name: exam!.name, weight: exam!.weight, exam_subjects: [...exam!.exam_subjects.map(existingToInput), form] }),
    onSuccess: () => {
      toast.success('Subject added.')
      queryClient.invalidateQueries({ queryKey: queryKeys.exam(id) })
      setModalOpen(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  function existingToInput(es: { subject?: { id: number }; section?: { id: number }; grading_scale_id: number | null; max_marks: number; passing_marks: number | null; exam_date: string | null; is_online: boolean; duration_minutes: number | null; online_starts_at: string | null; online_ends_at: string | null; shuffle_questions: boolean; max_attempts: number }): ExamSubjectInput {
    return {
      subject_id: es.subject!.id, section_id: es.section!.id, grading_scale_id: es.grading_scale_id,
      max_marks: es.max_marks, passing_marks: es.passing_marks, exam_date: es.exam_date,
      is_online: es.is_online, duration_minutes: es.duration_minutes, online_starts_at: es.online_starts_at,
      online_ends_at: es.online_ends_at, shuffle_questions: es.shuffle_questions, max_attempts: es.max_attempts,
    }
  }

  if (isLoading || !exam) {
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
        title={exam.name}
        breadcrumbs={[{ label: 'Exams', to: routePaths.exams }, { label: exam.name }]}
        actions={
          <>
            <Badge variant={exam.is_published ? 'success' : 'default'}>{exam.is_published ? 'Published' : 'Draft'}</Badge>
            {can('exams.publish') && !exam.is_published && (
              <Button onClick={() => publishMutation.mutate()} isLoading={publishMutation.isPending}>
                <CheckCircle2 className="h-4 w-4" /> Publish
              </Button>
            )}
            {can('exams.publish') && exam.is_published && (
              <Button variant="outline" onClick={() => unpublishMutation.mutate()} isLoading={unpublishMutation.isPending}>
                <XCircle className="h-4 w-4" /> Unpublish
              </Button>
            )}
          </>
        }
      />

      <div className="mb-6 flex items-center justify-between">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Subjects</h2>
        {can('exams.edit') && (
          <Button size="sm" onClick={() => { setForm(EMPTY_SUBJECT); setModalOpen(true) }}>
            <Plus className="h-4 w-4" /> Add Subject
          </Button>
        )}
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Subject</TableHead>
            <TableHead>Section</TableHead>
            <TableHead>Max Marks</TableHead>
            <TableHead>Mode</TableHead>
            <TableHead></TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {exam.exam_subjects.length === 0 && (
            <TableRow><TableCell colSpan={5} className="text-center text-sm text-muted-foreground">No subjects added yet.</TableCell></TableRow>
          )}
          {exam.exam_subjects.map((es) => (
            <TableRow key={es.id}>
              <TableCell className="font-medium">{es.subject?.name}</TableCell>
              <TableCell>{es.section?.name}</TableCell>
              <TableCell>{es.max_marks}</TableCell>
              <TableCell>{es.is_online ? <Badge variant="info">Online</Badge> : <Badge variant="outline">Offline</Badge>}</TableCell>
              <TableCell className="text-right">
                {es.is_online ? (
                  <Button variant="outline" size="sm" onClick={() => navigate(routePaths.examSubjectOnlineTest(exam.id, es.id))}>
                    <ListChecks className="h-3.5 w-3.5" /> Configure Test
                  </Button>
                ) : (
                  <Button variant="outline" size="sm" onClick={() => navigate(routePaths.examSubjectMarks(exam.id, es.id))}>
                    <ClipboardList className="h-3.5 w-3.5" /> Enter Marks
                  </Button>
                )}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <Modal open={modalOpen} onOpenChange={setModalOpen} title="Add Subject to Exam" size="lg">
        <form onSubmit={(e) => { e.preventDefault(); addSubjectMutation.mutate() }} className="flex flex-col gap-4" noValidate>
          <div className="grid grid-cols-2 gap-4">
            <FormField label="Subject" htmlFor="subject_id" required>
              <Select id="subject_id" value={form.subject_id ? String(form.subject_id) : undefined} onValueChange={(v) => setForm({ ...form, subject_id: Number(v) })} options={(subjects?.data ?? []).map((s) => ({ value: String(s.id), label: s.name }))} />
            </FormField>
            <FormField label="Section" htmlFor="section_id" required>
              <Select id="section_id" value={form.section_id ? String(form.section_id) : undefined} onValueChange={(v) => setForm({ ...form, section_id: Number(v) })} options={(sections?.data ?? []).map((s) => ({ value: String(s.id), label: `${s.grade_level?.name ?? ''} - ${s.name}` }))} />
            </FormField>
            <FormField label="Max marks" htmlFor="max_marks" required>
              <Input id="max_marks" type="number" min="1" value={form.max_marks} onChange={(e) => setForm({ ...form, max_marks: Number(e.target.value) })} />
            </FormField>
            <FormField label="Passing marks" htmlFor="passing_marks" hint="Optional">
              <Input id="passing_marks" type="number" min="0" value={form.passing_marks ?? ''} onChange={(e) => setForm({ ...form, passing_marks: e.target.value ? Number(e.target.value) : null })} />
            </FormField>
            <FormField label="Exam date" htmlFor="exam_date" hint="Optional">
              <Input id="exam_date" type="date" value={form.exam_date ?? ''} onChange={(e) => setForm({ ...form, exam_date: e.target.value || null })} />
            </FormField>
          </div>

          <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={form.is_online ?? false} onCheckedChange={(checked) => setForm({ ...form, is_online: checked })} />
            This subject is an online test (auto-graded MCQ/True-False)
          </label>

          {form.is_online && (
            <div className="grid grid-cols-2 gap-4 rounded-md border border-border p-3">
              <FormField label="Duration (minutes)" htmlFor="duration_minutes">
                <Input id="duration_minutes" type="number" min="1" value={form.duration_minutes ?? ''} onChange={(e) => setForm({ ...form, duration_minutes: e.target.value ? Number(e.target.value) : null })} />
              </FormField>
              <FormField label="Max attempts" htmlFor="max_attempts">
                <Input id="max_attempts" type="number" min="1" max="10" value={form.max_attempts ?? 1} onChange={(e) => setForm({ ...form, max_attempts: Number(e.target.value) })} />
              </FormField>
              <FormField label="Opens at" htmlFor="online_starts_at" hint="Optional">
                <Input id="online_starts_at" type="datetime-local" value={form.online_starts_at ?? ''} onChange={(e) => setForm({ ...form, online_starts_at: e.target.value || null })} />
              </FormField>
              <FormField label="Closes at" htmlFor="online_ends_at" hint="Optional">
                <Input id="online_ends_at" type="datetime-local" value={form.online_ends_at ?? ''} onChange={(e) => setForm({ ...form, online_ends_at: e.target.value || null })} />
              </FormField>
              <label className="col-span-2 flex items-center gap-2 text-sm">
                <Checkbox checked={form.shuffle_questions ?? true} onCheckedChange={(checked) => setForm({ ...form, shuffle_questions: checked })} />
                Shuffle question order per student
              </label>
            </div>
          )}

          <Button type="submit" isLoading={addSubjectMutation.isPending} className="mt-2">Add subject</Button>
        </form>
      </Modal>
    </div>
  )
}
