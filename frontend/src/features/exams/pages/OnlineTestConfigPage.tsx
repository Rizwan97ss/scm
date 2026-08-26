import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'
import { useFieldArray, useForm } from 'react-hook-form'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { FileSpreadsheet, Plus, Trash2, Upload } from 'lucide-react'
import { examsApi, onlineTestsApi, questionsApi } from '@/api/endpoints/exams'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import {
  Button, Checkbox, ConfirmDialog, FormField, Input, Modal, Select, Skeleton,
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow, Textarea,
} from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import { QUESTION_TYPES } from '@/types/exam'
import { QUESTION_TYPE_TRANSLATION_KEY } from '../questionTypeLabels'
import type { Question, QuestionImportResult, QuestionOptionInput, QuestionPayload } from '@/types/exam'
import { formatApiError, type ApiError } from '@/api/client'

const EMPTY_OPTIONS: QuestionOptionInput[] = [
  { option_text: '', is_correct: true },
  { option_text: '', is_correct: false },
]

export function OnlineTestConfigPage() {
  const { t } = useTranslation()
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

  // Scoped to THIS test, not the subject at large — a question only shows up
  // here once it's been created or imported directly into this test (see
  // QuestionController::attachToTest / McqQuestionsImport's optional
  // exam-subject attach). There's no shared question bank to browse anymore:
  // a brand-new test starts with an empty list until you import or add one.
  const questionsQuery = useQuery({
    queryKey: queryKeys.onlineTestQuestions(examSubjectIdNum),
    queryFn: () => onlineTestsApi.questions(examSubjectIdNum),
    enabled: Number.isFinite(examSubjectIdNum),
  })
  const questions = questionsQuery.data ?? []

  function invalidateQuestions() {
    queryClient.invalidateQueries({ queryKey: queryKeys.onlineTestQuestions(examSubjectIdNum) })
  }

  const createMutation = useMutation({
    mutationFn: (payload: QuestionPayload) => questionsApi.create(payload),
    onSuccess: () => { toast.success(t('exams.questionCreatedToast')); invalidateQuestions() },
    onError: (error) => toast.error(formatApiError(error as ApiError)),
  })
  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<QuestionPayload> }) => questionsApi.update(id, payload),
    onSuccess: () => { toast.success(t('exams.questionUpdatedToast')); invalidateQuestions() },
    onError: (error) => toast.error(formatApiError(error as ApiError)),
  })
  const removeMutation = useMutation({
    mutationFn: (id: number) => questionsApi.remove(id),
    onSuccess: () => { toast.success(t('exams.questionDeletedToast')); invalidateQuestions() },
    onError: (error) => toast.error(formatApiError(error as ApiError)),
  })

  // ---- Question CRUD ----
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
      else await createMutation.mutateAsync({ ...values, subject_id: subjectId, exam_subject_id: examSubjectIdNum })
      setModalOpen(false)
    } catch {
      toast.error(t('exams.optionsValidationError'))
    }
  }

  // ---- Excel import — attaches straight into this test, not a subject-wide bank ----
  const [importModalOpen, setImportModalOpen] = useState(false)
  const [importResult, setImportResult] = useState<QuestionImportResult | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const importMutation = useMutation({
    mutationFn: (file: File) => questionsApi.import(file, subjectId!, examSubjectIdNum),
    onSuccess: (result) => {
      setImportResult(result)
      invalidateQuestions()
      if (result.failed_count === 0) toast.success(t('exams.questionsImportedToast', { count: result.imported_count }))
      else toast.warning(t('exams.importedWithFailuresToast', { imported: result.imported_count, failed: result.failed_count }))
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

  return (
    <div>
      <PageHeader
        title={t('exams.configureOnlineTestTitle', { subject: group?.subject?.name })}
        description={t('exams.configureOnlineTestDescription', { exam: exam.name, section: group?.section?.name })}
        breadcrumbs={[{ label: t('nav.exams'), to: routePaths.exams }, { label: exam.name, to: routePaths.examDetail(exam.id) }, { label: group?.subject?.name ?? '' }]}
        actions={
          <div className="flex flex-wrap gap-2">
            {can('questions.import') && (
              <Button variant="outline" onClick={() => { setImportResult(null); setImportModalOpen(true) }}>
                <Upload className="h-4 w-4" /> {t('exams.importFromExcel')}
              </Button>
            )}
            {can('questions.create') && (
              <Button variant="outline" onClick={openCreate}>
                <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.question') })}
              </Button>
            )}
          </div>
        }
      />

      {questionsQuery.isLoading && <Skeleton className="h-64 w-full" />}

      {!questionsQuery.isLoading && (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('entities.question')}</TableHead>
              <TableHead>{t('exams.type')}</TableHead>
              <TableHead>{t('exams.marks')}</TableHead>
              <TableHead></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {questions.map((question) => (
              <TableRow key={question.id}>
                <TableCell className="max-w-md">
                  <span className="line-clamp-2">{question.text}</span>
                </TableCell>
                <TableCell>{t(QUESTION_TYPE_TRANSLATION_KEY[question.type])}</TableCell>
                <TableCell>{question.default_marks}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-2">
                    {can('questions.edit') && <Button variant="outline" size="sm" onClick={() => openEdit(question)}>{t('common.edit')}</Button>}
                    {can('questions.delete') && (
                      <Button variant="outline" size="sm" onClick={() => setDeleting(question)} aria-label={t('common.deleteItem', { item: t('entities.question') })}>
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))}
            {questions.length === 0 && (
              <TableRow><TableCell colSpan={4} className="text-center text-sm text-muted-foreground">{t('exams.noQuestionsYetHint')}</TableCell></TableRow>
            )}
          </TableBody>
        </Table>
      )}

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? t('common.editItem', { item: t('entities.question') }) : t('common.newItem', { item: t('entities.question') })} size="lg">
        <form onSubmit={handleSubmit(onSubmitQuestion)} className="flex flex-col gap-4" noValidate>
          <FormField label={t('exams.type')} htmlFor="type" required>
            <Select
              id="type"
              value={type}
              onValueChange={(value) => switchType(value as 'mcq' | 'true_false')}
              options={QUESTION_TYPES.map((qt) => ({ value: qt, label: t(QUESTION_TYPE_TRANSLATION_KEY[qt]) }))}
            />
          </FormField>
          <FormField label={t('exams.questionText')} htmlFor="text" required error={errors.text?.message}>
            <Textarea id="text" required {...register('text', { required: t('common.required') })} />
          </FormField>
          <div className="grid grid-cols-2 gap-4">
            <FormField label={t('exams.defaultMarks')} htmlFor="default_marks" required>
              <Input id="default_marks" type="number" step="0.01" min="0.01" {...register('default_marks', { valueAsNumber: true, required: true, min: 0.01 })} />
            </FormField>
            <FormField label={t('exams.negativeMarks')} htmlFor="negative_marks" hint={t('exams.negativeMarksHint')}>
              <Input id="negative_marks" type="number" step="0.01" min="0" {...register('negative_marks', { valueAsNumber: true, min: 0 })} />
            </FormField>
          </div>
          <FormField label={t('exams.explanation')} htmlFor="explanation" hint={t('exams.explanationHint')}>
            <Textarea id="explanation" {...register('explanation')} />
          </FormField>

          <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold">{t('exams.options')}</h3>
              {type === 'mcq' && (
                <Button type="button" variant="outline" size="sm" onClick={() => append({ option_text: '', is_correct: false })}>
                  <Plus className="h-3.5 w-3.5" /> {t('exams.addOption')}
                </Button>
              )}
            </div>
            {fields.map((field, index) => (
              <div key={field.id} className="flex items-center gap-2">
                <Checkbox checked={watch(`options.${index}.is_correct`)} onCheckedChange={() => setCorrectOption(index)} aria-label={t('exams.markOptionCorrectAriaLabel', { number: index + 1 })} />
                <Input
                  className="flex-1"
                  placeholder={t('exams.optionPlaceholder', { number: index + 1 })}
                  disabled={type === 'true_false'}
                  {...register(`options.${index}.option_text`, { required: true })}
                />
                {type === 'mcq' && (
                  <Button type="button" variant="outline" size="icon" onClick={() => remove(index)} disabled={fields.length <= 2} aria-label={t('exams.removeOption')}>
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                )}
              </div>
            ))}
            <p className="text-xs text-muted-foreground">{t('exams.checkCorrectOptionHint')}</p>
          </div>

          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.question') })}
          </Button>
        </form>
      </Modal>

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('exams.deleteQuestionConfirmTitle')}
        description={t('exams.deleteQuestionConfirmDescription')}
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />

      <Modal open={importModalOpen} onOpenChange={setImportModalOpen} title={t('exams.importQuestionsTitle', { subject: group?.subject?.name })}>
        <div className="flex flex-col gap-4">
          <p className="text-sm text-muted-foreground">
            {t('exams.importQuestionsInstructions')}{' '}
            <a href={questionsApi.importTemplateUrl()} target="_blank" rel="noopener" className="inline-flex items-center gap-1 text-primary hover:underline">
              <FileSpreadsheet className="h-3.5 w-3.5" /> {t('exams.downloadTemplate')}
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
            <Upload className="h-4 w-4" /> {t('exams.chooseFileToImport')}
          </Button>

          {importResult && (
            <div className="flex flex-col gap-2 rounded-md border border-border p-3 text-sm">
              <p>
                <span className="font-medium text-success">{t('exams.importedCount', { count: importResult.imported_count })}</span>
                {importResult.failed_count > 0 && <span className="text-destructive"> · {t('exams.failedCount', { count: importResult.failed_count })}</span>}
              </p>
              {importResult.failures.length > 0 && (
                <ul className="flex flex-col gap-1 text-xs text-muted-foreground">
                  {importResult.failures.map((f, i) => (
                    <li key={i}>{t('exams.importRowError', { row: f.row, attribute: f.attribute, errors: f.errors.join(' ') })}</li>
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
