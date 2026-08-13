import { useRef, useState } from 'react'
import { useFieldArray, useForm } from 'react-hook-form'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { FileSpreadsheet, Plus, Trash2, Upload } from 'lucide-react'
import { questionsApi } from '@/api/endpoints/exams'
import { subjectsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Checkbox, ConfirmDialog, DataTable, FormField, Input, Modal, Select, Textarea, type DataTableColumn } from '@/components/ui'
import { QUESTION_TYPES, QUESTION_TYPE_LABELS } from '@/types/exam'
import type { Question, QuestionImportResult, QuestionOptionInput, QuestionPayload } from '@/types/exam'
import type { ApiError } from '@/api/client'

const EMPTY_OPTIONS: QuestionOptionInput[] = [
  { option_text: '', is_correct: true },
  { option_text: '', is_correct: false },
]

export function QuestionBankPage() {
  const { can } = usePermission()
  const { setPage, queryParams } = usePagination('created_at')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(questionsApi, queryKeys.questions, queryParams, 'Question')
  const { data: subjects } = useQuery({ queryKey: queryKeys.subjects({ per_page: 100 }), queryFn: () => subjectsApi.list({ per_page: 100 }) })

  const queryClient = useQueryClient()
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Question | null>(null)
  const [deleting, setDeleting] = useState<Question | null>(null)

  const [importModalOpen, setImportModalOpen] = useState(false)
  const [importSubjectId, setImportSubjectId] = useState<number | undefined>(undefined)
  const [importResult, setImportResult] = useState<QuestionImportResult | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const importMutation = useMutation({
    mutationFn: (file: File) => questionsApi.import(file, importSubjectId!),
    onSuccess: (result) => {
      setImportResult(result)
      queryClient.invalidateQueries({ queryKey: ['questions'] })
      if (result.failed_count === 0) toast.success(`${result.imported_count} question(s) imported.`)
      else toast.warning(`${result.imported_count} imported, ${result.failed_count} row(s) failed.`)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const { register, control, handleSubmit, reset, watch, setValue, formState: { errors } } = useForm<QuestionPayload>({
    defaultValues: { type: 'mcq', text: '', default_marks: 1, negative_marks: null, options: EMPTY_OPTIONS },
  })
  const { fields, append, remove } = useFieldArray({ control, name: 'options' })
  const type = watch('type')

  function openCreate() {
    setEditing(null)
    reset({ type: 'mcq', text: '', default_marks: 1, negative_marks: null, subject_id: undefined, options: EMPTY_OPTIONS })
    setModalOpen(true)
  }

  function openEdit(question: Question) {
    setEditing(question)
    reset({
      subject_id: question.subject?.id,
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

  async function onSubmit(values: QuestionPayload) {
    try {
      if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: values })
      else await createMutation.mutateAsync(values)
      setModalOpen(false)
    } catch {
      toast.error('Check the options — exactly one must be marked correct.')
    }
  }

  const columns: DataTableColumn<Question>[] = [
    { key: 'text', header: 'Question', render: (row) => <span className="line-clamp-1 font-medium">{row.text}</span> },
    { key: 'type', header: 'Type', render: (row) => <Badge variant="outline">{QUESTION_TYPE_LABELS[row.type]}</Badge> },
    { key: 'subject', header: 'Subject', render: (row) => row.subject?.name ?? '—' },
    { key: 'marks', header: 'Marks', render: (row) => row.default_marks },
    {
      key: 'actions', header: '', align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {can('questions.edit') && <Button variant="outline" size="sm" onClick={() => openEdit(row)}>Edit</Button>}
          {can('questions.delete') && (
            <Button variant="outline" size="sm" onClick={() => setDeleting(row)} aria-label={`Delete question`}>
              <Trash2 className="h-3.5 w-3.5" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title="Question Bank"
        description="Multiple-choice and True/False questions for online tests — auto-graded, exactly one correct answer each."
        actions={
          can('questions.create') && (
            <>
              {can('questions.import') && (
                <Button variant="outline" onClick={() => { setImportResult(null); setImportSubjectId(undefined); setImportModalOpen(true) }}>
                  <Upload className="h-4 w-4" /> Import from Excel
                </Button>
              )}
              <Button onClick={openCreate}><Plus className="h-4 w-4" /> New Question</Button>
            </>
          )
        }
      />
      <DataTable columns={columns} data={listQuery.data?.data} rowKey={(r) => r.id} isLoading={listQuery.isLoading} meta={listQuery.data?.meta} onPageChange={setPage} emptyTitle="No questions yet" />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Question' : 'New Question'} size="lg">
        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
          <FormField label="Type" htmlFor="type" required>
            <Select
              id="type"
              value={type}
              onValueChange={(value) => switchType(value as 'mcq' | 'true_false')}
              options={QUESTION_TYPES.map((t) => ({ value: t, label: QUESTION_TYPE_LABELS[t] }))}
            />
          </FormField>
          <FormField label="Subject" htmlFor="subject_id" hint="Optional">
            <Select
              id="subject_id"
              value={watch('subject_id') ? String(watch('subject_id')) : undefined}
              onValueChange={(value) => setValue('subject_id', Number(value))}
              options={(subjects?.data ?? []).map((s) => ({ value: String(s.id), label: s.name }))}
              placeholder="Any subject"
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

      <Modal open={importModalOpen} onOpenChange={setImportModalOpen} title="Import Questions from Excel">
        <div className="flex flex-col gap-4">
          <p className="text-sm text-muted-foreground">
            Columns: question, option_a, option_b, option_c, option_d, correct_option, marks, negative_marks (optional).{' '}
            <a href={questionsApi.importTemplateUrl()} target="_blank" rel="noopener" className="inline-flex items-center gap-1 text-primary hover:underline">
              <FileSpreadsheet className="h-3.5 w-3.5" /> Download template
            </a>
          </p>

          <FormField label="Subject" htmlFor="import_subject_id" required>
            <Select
              id="import_subject_id"
              value={importSubjectId ? String(importSubjectId) : undefined}
              onValueChange={(value) => setImportSubjectId(Number(value))}
              options={(subjects?.data ?? []).map((s) => ({ value: String(s.id), label: s.name }))}
            />
          </FormField>

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
          <Button type="button" variant="outline" disabled={!importSubjectId} isLoading={importMutation.isPending} onClick={() => fileInputRef.current?.click()}>
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
