import { useRef, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useFieldArray, useForm } from 'react-hook-form'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { FileSpreadsheet, Plus, Save, Trash2, Upload } from 'lucide-react'
import { examsApi, onlineTestsApi, questionsApi } from '@/api/endpoints/exams'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import {
  Button, Checkbox, ConfirmDialog, FormField, Input, Modal, Select, Skeleton,
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow, Textarea,
} from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import { QUESTION_TYPES, QUESTION_TYPE_LABELS } from '@/types/exam'
import type { Question, QuestionImportResult, QuestionOptionInput, QuestionPayload } from '@/types/exam'
import { formatApiError, type ApiError } from '@/api/client'

const EMPTY_OPTIONS: QuestionOptionInput[] = [
  { option_text: '', is_correct: true },
  { option_text: '', is_correct: false },
]

export function OnlineTestConfigPage() {
  const { examId, examSubjectId } = useParams<{ examId: string; examSubjectId: string }>()
  const examSubjectIdNum = Number(examSubjectId)
  const { can } = usePermission()
  const queryClient = useQueryClient()

  const { data: exam } = useQuery({ queryKey: queryKeys.exam(Number(examId)), queryFn: () => examsApi.get(Number(examId)) })
  // The component (what the test is configured on) and its parent group
  // (what subject/section it's under) — see ExamSubjectGroup in types/exam.ts.
  const group = exam?.exam_subject_groups.find((g) => g.components.some((c) => c.id === examSubjectIdNum))
  const examSubject = group?.components.find((c) => c.id === examSubjectIdNum)
  const subjectId = group?.subject?.id

  // Scoped to this component's own subject — this used to list every question
  // app-wide regardless of subject, a leftover from before questions had a
  // subject_id at all. QuestionController already supports filter[subject_id].
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(
    questionsApi, queryKeys.questions, { 'filter[subject_id]': subjectId, per_page: 200 }, 'Question'
  )
  const questions = listQuery.data

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
    onError: (error) => toast.error(formatApiError(error as ApiError)),
  })

  function toggle(questionId: number, defaultMarks: number) {
    setSelected((prev) => {
      const next = { ...prev }
      if (questionId in next) delete next[questionId]
      else next[questionId] = defaultMarks
      return next
    })
  }

  // ---- Question CRUD (folded in from the now-removed standalone Question Bank page) ----
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Question | null>(null)
  const [deleting, setDeleting] = useState<Question | null>(null)

  const { register, control, handleSubmit, reset, watch, setValue, formState: { errors } } = useForm<QuestionPayload>({
    defaultValues: { type: 'mcq', text: '', default_marks: 1, negative_marks: null, options: EMPTY_OPTIONS },
  })
  const { fields, append, remove } = useFieldArray({ control, name: 'options' })
  const type = watch('type')

  function openCreate() {
    setEditing(null)
    reset({ type: 'mcq', text: '', default_marks: 1, negative_marks: null, subject_id: subjectId, options: EMPTY_OPTIONS })
    setModalOpen(true)
  }

  function openEdit(question: Question) {
    setEditing(question)
    reset({
      subject_id: question.subject?.id ?? subjectId,
      type: question.type,
      text: question.text,
      default_marks: question.default_marks,
      negative_marks: question.negative_marks,
      explanation: question.explanation,
      options: question.options.map((o) => ({ option_text: o.option_text, is_correct: o.is_correct })),
    })
    setModalOpen(true)
  }

  function switchType(nextType: 'mcq' | 'true_false') {
    setValue('type', nextType)
    if (nextType === 'true_false') {
      setValue('options', [
        { option_text: 'True', is_correct: true },
        { option_text: 'False', is_correct: false },
      ])
    }
  }

  function setCorrectOption(index: number) {
    fields.forEach((_, i) => setValue(`options.${i}.is_correct`, i === index))
  }

  async function onSubmitQuestion(values: QuestionPayload) {
    try {
      if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: values })
      else await createMutation.mutateAsync({ ...values, subject_id: subjectId })
      setModalOpen(false)
    } catch {
      toast.error('Check the options — exactly one must be marked correct.')
    }
  }

  // ---- Excel import (folded in from the now-removed standalone Question Bank page) ----
  const [importModalOpen, setImportModalOpen] = useState(false)
  const [importResult, setImportResult] = useState<QuestionImportResult | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const importMutation = useMutation({
    mutationFn: (file: File) => questionsApi.import(file, subjectId!),
    onSuccess: (result) => {
      setImportResult(result)
      queryClient.invalidateQueries({ queryKey: ['questions'] })
      if (result.failed_count === 0) toast.success(`${result.imported_count} question(s) imported.`)
      else toast.warning(`${result.imported_count} imported, ${result.failed_count} row(s) failed.`)
    },
    onError: (error) => toast.error(formatApiError(error as ApiError)),
  })

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
          <div className="flex flex-wrap gap-2">
            {can('questions.import') && (
              <Button variant="outline" onClick={() => { setImportResult(null); setImportModalOpen(true) }}>
                <Upload className="h-4 w-4" /> Import from Excel
              </Button>
            )}
            {can('questions.create') && (
              <Button variant="outline" onClick={openCreate}>
                <Plus className="h-4 w-4" /> New Question
              </Button>
            )}
            <Button onClick={() => saveMutation.mutate()} isLoading={saveMutation.isPending} disabled={selectedCount === 0}>
              <Save className="h-4 w-4" /> Save ({selectedCount} selected)
            </Button>
          </div>
        }
      />

      {listQuery.isLoading && <Skeleton className="h-64 w-full" />}

      {!listQuery.isLoading && (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead></TableHead>
              <TableHead>Question</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Marks</TableHead>
              <TableHead></TableHead>
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
                <TableCell className="text-right">
                  <div className="flex justify-end gap-2">
                    {can('questions.edit') && <Button variant="outline" size="sm" onClick={() => openEdit(question)}>Edit</Button>}
                    {can('questions.delete') && (
                      <Button variant="outline" size="sm" onClick={() => setDeleting(question)} aria-label="Delete question">
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))}
            {(questions?.data ?? []).length === 0 && (
              <TableRow><TableCell colSpan={5} className="text-center text-sm text-muted-foreground">No questions for this subject yet.</TableCell></TableRow>
            )}
          </TableBody>
        </Table>
      )}

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Question' : 'New Question'} size="lg">
        <form onSubmit={handleSubmit(onSubmitQuestion)} className="flex flex-col gap-4" noValidate>
          <FormField label="Type" htmlFor="type" required>
            <Select
              id="type"
              value={type}
              onValueChange={(value) => switchType(value as 'mcq' | 'true_false')}
              options={QUESTION_TYPES.map((t) => ({ value: t, label: QUESTION_TYPE_LABELS[t] }))}
            />
          </FormField>
          <FormField label="Question text" htmlFor="text" required error={errors.text?.message}>
            <Textarea id="text" required {...register('text', { required: 'Required' })} />
          </FormField>
          <div className="grid grid-cols-2 gap-4">
            <FormField label="Default marks" htmlFor="default_marks" required>
              <Input id="default_marks" type="number" step="0.01" min="0.01" {...register('default_marks', { valueAsNumber: true, required: true, min: 0.01 })} />
            </FormField>
            <FormField label="Negative marks" htmlFor="negative_marks" hint="Optional — deducted for a wrong (not blank) answer">
              <Input id="negative_marks" type="number" step="0.01" min="0" {...register('negative_marks', { valueAsNumber: true, min: 0 })} />
            </FormField>
          </div>
          <FormField label="Explanation" htmlFor="explanation" hint="Shown to the student after the test is graded">
            <Textarea id="explanation" {...register('explanation')} />
          </FormField>

          <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold">Options</h3>
              {type === 'mcq' && (
                <Button type="button" variant="outline" size="sm" onClick={() => append({ option_text: '', is_correct: false })}>
                  <Plus className="h-3.5 w-3.5" /> Add option
                </Button>
              )}
            </div>
            {fields.map((field, index) => (
              <div key={field.id} className="flex items-center gap-2">
                <Checkbox checked={watch(`options.${index}.is_correct`)} onCheckedChange={() => setCorrectOption(index)} aria-label={`Mark option ${index + 1} correct`} />
                <Input
                  className="flex-1"
                  placeholder={`Option ${index + 1}`}
                  disabled={type === 'true_false'}
                  {...register(`options.${index}.option_text`, { required: true })}
                />
                {type === 'mcq' && (
                  <Button type="button" variant="outline" size="icon" onClick={() => remove(index)} disabled={fields.length <= 2} aria-label="Remove option">
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                )}
              </div>
            ))}
            <p className="text-xs text-muted-foreground">Check the box next to the correct option.</p>
          </div>

          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? 'Save changes' : 'Create question'}
          </Button>
        </form>
      </Modal>

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title="Delete this question?"
        description="It will be removed from any online tests it's attached to."
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />

      <Modal open={importModalOpen} onOpenChange={setImportModalOpen} title={`Import Questions for ${group?.subject?.name}`}>
        <div className="flex flex-col gap-4">
          <p className="text-sm text-muted-foreground">
            Columns: question, option_a, option_b, option_c, option_d, correct_option, marks, negative_marks (optional).{' '}
            <a href={questionsApi.importTemplateUrl()} target="_blank" rel="noopener" className="inline-flex items-center gap-1 text-primary hover:underline">
              <FileSpreadsheet className="h-3.5 w-3.5" /> Download template
            </a>
          </p>

          <input
            ref={fileInputRef}
            type="file"
            accept=".xlsx,.xls,.csv"
            className="hidden"
            onChange={(e) => {
              const file = e.target.files?.[0]
              if (file) importMutation.mutate(file)
              e.target.value = ''
            }}
          />
          <Button type="button" variant="outline" isLoading={importMutation.isPending} onClick={() => fileInputRef.current?.click()}>
            <Upload className="h-4 w-4" /> Choose file to import
          </Button>

          {importResult && (
            <div className="flex flex-col gap-2 rounded-md border border-border p-3 text-sm">
              <p>
                <span className="font-medium text-success">{importResult.imported_count} imported</span>
                {importResult.failed_count > 0 && <span className="text-destructive"> · {importResult.failed_count} failed</span>}
              </p>
              {importResult.failures.length > 0 && (
                <ul className="flex flex-col gap-1 text-xs text-muted-foreground">
                  {importResult.failures.map((f, i) => (
                    <li key={i}>Row {f.row} ({f.attribute}): {f.errors.join(' ')}</li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </div>
      </Modal>
    </div>
  )
}
