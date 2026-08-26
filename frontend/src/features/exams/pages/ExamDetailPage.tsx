import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams, useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CheckCircle2, ClipboardList, ListChecks, Plus, XCircle } from 'lucide-react'
import { assessmentComponentTypesApi, examsApi } from '@/api/endpoints/exams'
import { sectionsApi, subjectsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Checkbox, FormField, Input, Modal, Select, Skeleton, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import { fromDatetimeLocalInput, toDatetimeLocalInput } from '@/utils/formatDate'
import type { ExamSubject, ExamSubjectComponentInput, ExamSubjectGroup, ExamSubjectGroupInput } from '@/types/exam'
import type { ApiError } from '@/api/client'

const EMPTY_COMPONENT: ExamSubjectComponentInput = { assessment_component_type_id: 0, max_marks: 100, is_online: false, max_attempts: 1, shuffle_questions: true }
const EMPTY_GROUP: Omit<ExamSubjectGroupInput, 'components'> = { subject_id: 0, section_id: 0 }

function componentToInput(component: ExamSubject): ExamSubjectComponentInput {
  return {
    assessment_component_type_id: component.assessment_component_type?.id ?? 0,
    max_marks: component.max_marks,
    sequence: component.sequence,
    exam_date: component.exam_date,
    is_online: component.is_online,
    duration_minutes: component.duration_minutes,
    online_starts_at: component.online_starts_at,
    online_ends_at: component.online_ends_at,
    shuffle_questions: component.shuffle_questions,
    max_attempts: component.max_attempts,
  }
}

function groupToInput(group: ExamSubjectGroup): ExamSubjectGroupInput {
  return {
    subject_id: group.subject!.id,
    section_id: group.section!.id,
    grading_scale_id: group.grading_scale_id,
    passing_marks: group.passing_marks,
    components: group.components.map(componentToInput),
  }
}

export function ExamDetailPage() {
  const { t } = useTranslation()
  const { examId } = useParams<{ examId: string }>()
  const id = Number(examId)
  const { can } = usePermission()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data: exam, isLoading } = useQuery({ queryKey: queryKeys.exam(id), queryFn: () => examsApi.get(id) })
  const { data: subjects } = useQuery({ queryKey: queryKeys.subjects({ per_page: 100 }), queryFn: () => subjectsApi.list({ per_page: 100 }) })
  const { data: sections } = useQuery({ queryKey: queryKeys.sections({ per_page: 100 }), queryFn: () => sectionsApi.list({ per_page: 100 }) })
  // Only the exams.edit-gated "Add Subject"/"Add Component" modals consume this — a
  // Class Teacher (exams.view but not exams.edit, and no grading.view either) would
  // otherwise get a needless 403 on every page load for data they can never act on.
  const { data: componentTypes } = useQuery({ queryKey: queryKeys.assessmentComponentTypes(), queryFn: () => assessmentComponentTypesApi.list(), enabled: can('exams.edit') })

  const [groupModalOpen, setGroupModalOpen] = useState(false)
  const [groupForm, setGroupForm] = useState(EMPTY_GROUP)
  const [firstComponent, setFirstComponent] = useState<ExamSubjectComponentInput>(EMPTY_COMPONENT)

  const [componentModalOpen, setComponentModalOpen] = useState(false)
  const [targetGroup, setTargetGroup] = useState<ExamSubjectGroup | null>(null)
  const [componentForm, setComponentForm] = useState<ExamSubjectComponentInput>(EMPTY_COMPONENT)

  const selectedComponentType = componentTypes?.data?.find((t) => t.id === firstComponent.assessment_component_type_id)
  const selectedTargetComponentType = componentTypes?.data?.find((t) => t.id === componentForm.assessment_component_type_id)

  const publishMutation = useMutation({
    mutationFn: () => examsApi.publish(id),
    onSuccess: () => {
      toast.success(t('exams.examPublishedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.exam(id) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })
  const unpublishMutation = useMutation({
    mutationFn: () => examsApi.unpublish(id),
    onSuccess: () => {
      toast.success(t('exams.examUnpublishedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.exam(id) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const publishGroupMutation = useMutation({
    mutationFn: (groupId: number) => examsApi.publishGroup(id, groupId),
    onSuccess: () => {
      toast.success(t('exams.resultDeclaredToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.exam(id) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })
  const unpublishGroupMutation = useMutation({
    mutationFn: (groupId: number) => examsApi.unpublishGroup(id, groupId),
    onSuccess: () => {
      toast.success(t('exams.resultUndeclaredToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.exam(id) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const addGroupMutation = useMutation({
    mutationFn: () =>
      examsApi.update(id, {
        academic_year_id: exam!.academic_year_id, term_id: exam!.term_id, exam_type_id: exam!.exam_type?.id, name: exam!.name, weight: exam!.weight,
        exam_subject_groups: [...exam!.exam_subject_groups.map(groupToInput), { ...groupForm, components: [firstComponent] }],
      }),
    onSuccess: () => {
      toast.success(t('exams.subjectAddedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.exam(id) })
      setGroupModalOpen(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const addComponentMutation = useMutation({
    mutationFn: () =>
      examsApi.update(id, {
        academic_year_id: exam!.academic_year_id, term_id: exam!.term_id, exam_type_id: exam!.exam_type?.id, name: exam!.name, weight: exam!.weight,
        exam_subject_groups: exam!.exam_subject_groups.map((g) =>
          g.id === targetGroup?.id ? { ...groupToInput(g), components: [...g.components.map(componentToInput), componentForm] } : groupToInput(g)
        ),
      }),
    onSuccess: () => {
      toast.success(t('exams.componentAddedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.exam(id) })
      setComponentModalOpen(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  function openAddComponent(group: ExamSubjectGroup) {
    setTargetGroup(group)
    setComponentForm(EMPTY_COMPONENT)
    setComponentModalOpen(true)
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
        breadcrumbs={[{ label: t('nav.exams'), to: routePaths.exams }, { label: exam.name }]}
        actions={
          <>
            <Badge variant={exam.is_published ? 'success' : 'default'}>{exam.is_published ? t('exams.published') : t('exams.draft')}</Badge>
            {can('exams.publish') && !exam.is_published && (
              <Button onClick={() => publishMutation.mutate()} isLoading={publishMutation.isPending}>
                <CheckCircle2 className="h-4 w-4" /> {t('exams.publish')}
              </Button>
            )}
            {can('exams.publish') && exam.is_published && (
              <Button variant="outline" onClick={() => unpublishMutation.mutate()} isLoading={unpublishMutation.isPending}>
                <XCircle className="h-4 w-4" /> {t('exams.unpublish')}
              </Button>
            )}
          </>
        }
      />

      <div className="mb-6 flex items-center justify-between">
        <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">{t('nav.subjects')}</h2>
        {can('exams.edit') && (
          <Button size="sm" onClick={() => { setGroupForm(EMPTY_GROUP); setFirstComponent(EMPTY_COMPONENT); setGroupModalOpen(true) }}>
            <Plus className="h-4 w-4" /> {t('exams.addSubject')}
          </Button>
        )}
      </div>

      <div className="flex flex-col gap-6">
        {exam.exam_subject_groups.length === 0 && <p className="text-center text-sm text-muted-foreground">{t('exams.noSubjectsAddedYet')}</p>}

        {exam.exam_subject_groups.map((group) => (
          <div key={group.id} className="rounded-lg border border-border">
            <div className="flex items-center justify-between border-b border-border bg-muted/40 px-4 py-3">
              <div className="flex items-center gap-2">
                <span className="font-medium">{group.subject?.name}</span>
                <span className="text-sm text-muted-foreground">{group.section?.name}</span>
                <Badge variant={group.published_at ? 'success' : 'default'}>{group.published_at ? t('exams.published') : t('exams.draft')}</Badge>
              </div>
              <div className="flex items-center gap-2">
                {can('exams.edit') && (
                  <Button variant="outline" size="sm" onClick={() => openAddComponent(group)}>
                    <Plus className="h-3.5 w-3.5" /> {t('exams.addComponent')}
                  </Button>
                )}
                {can('exam-marks.publish') && !group.published_at && (
                  <Button size="sm" onClick={() => publishGroupMutation.mutate(group.id)} isLoading={publishGroupMutation.isPending}>
                    {t('exams.declareResult')}
                  </Button>
                )}
                {can('exam-marks.publish') && group.published_at && (
                  <Button variant="outline" size="sm" onClick={() => unpublishGroupMutation.mutate(group.id)} isLoading={unpublishGroupMutation.isPending}>
                    {t('exams.undeclareResult')}
                  </Button>
                )}
              </div>
            </div>

            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('exams.component')}</TableHead>
                  <TableHead>{t('exams.maxMarks')}</TableHead>
                  <TableHead>{t('exams.mode')}</TableHead>
                  <TableHead></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {group.components.map((component) => (
                  <TableRow key={component.id}>
                    <TableCell className="font-medium">{component.assessment_component_type?.name ?? '—'}</TableCell>
                    <TableCell>{component.max_marks}</TableCell>
                    <TableCell>{component.is_online ? <Badge variant="info">{t('exams.online')}</Badge> : <Badge variant="outline">{t('exams.offline')}</Badge>}</TableCell>
                    <TableCell className="text-end">
                      {component.is_online ? (
                        <Button variant="outline" size="sm" onClick={() => navigate(routePaths.examSubjectOnlineTest(exam.id, component.id))}>
                          <ListChecks className="h-3.5 w-3.5" /> {t('exams.configureTest')}
                        </Button>
                      ) : (
                        <Button variant="outline" size="sm" onClick={() => navigate(routePaths.examSubjectMarks(exam.id, component.id))}>
                          <ClipboardList className="h-3.5 w-3.5" /> {t('exams.enterMarks')}
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        ))}
      </div>

      <Modal open={groupModalOpen} onOpenChange={setGroupModalOpen} title={t('exams.addSubjectToExam')} size="lg">
        <form onSubmit={(e) => { e.preventDefault(); addGroupMutation.mutate() }} className="flex flex-col gap-4" noValidate>
          <div className="grid grid-cols-2 gap-4">
            <FormField label={t('entities.subject')} htmlFor="subject_id" required>
              <Select id="subject_id" value={groupForm.subject_id ? String(groupForm.subject_id) : undefined} onValueChange={(v) => setGroupForm({ ...groupForm, subject_id: Number(v) })} options={(subjects?.data ?? []).map((s) => ({ value: String(s.id), label: s.name }))} />
            </FormField>
            <FormField label={t('entities.section')} htmlFor="section_id" required>
              <Select id="section_id" value={groupForm.section_id ? String(groupForm.section_id) : undefined} onValueChange={(v) => setGroupForm({ ...groupForm, section_id: Number(v) })} options={(sections?.data ?? []).map((s) => ({ value: String(s.id), label: `${s.grade_level?.name ?? ''} - ${s.name}` }))} />
            </FormField>
            <FormField label={t('exams.passingMarks')} htmlFor="passing_marks" hint={t('exams.passingMarksHint')}>
              <Input id="passing_marks" type="number" min="0" value={groupForm.passing_marks ?? ''} onChange={(e) => setGroupForm({ ...groupForm, passing_marks: e.target.value ? Number(e.target.value) : null })} />
            </FormField>
          </div>

          <div className="rounded-md border border-border p-3">
            <p className="mb-3 text-sm font-medium">{t('exams.firstComponent')}</p>
            <ComponentFields
              value={firstComponent}
              onChange={setFirstComponent}
              componentTypeOptions={componentTypes?.data ?? []}
              isAutoGraded={selectedComponentType?.is_auto_graded ?? false}
            />
          </div>

          <Button type="submit" isLoading={addGroupMutation.isPending} className="mt-2">{t('exams.addSubject')}</Button>
        </form>
      </Modal>

      <Modal open={componentModalOpen} onOpenChange={setComponentModalOpen} title={t('exams.addComponentModalTitle', { subject: targetGroup?.subject?.name ?? '' })} size="lg">
        <form onSubmit={(e) => { e.preventDefault(); addComponentMutation.mutate() }} className="flex flex-col gap-4" noValidate>
          <ComponentFields
            value={componentForm}
            onChange={setComponentForm}
            componentTypeOptions={componentTypes?.data ?? []}
            isAutoGraded={selectedTargetComponentType?.is_auto_graded ?? false}
          />
          <Button type="submit" isLoading={addComponentMutation.isPending} className="mt-2">{t('exams.addComponent')}</Button>
        </form>
      </Modal>
    </div>
  )
}

function ComponentFields({
  value,
  onChange,
  componentTypeOptions,
  isAutoGraded,
}: {
  value: ExamSubjectComponentInput
  onChange: (value: ExamSubjectComponentInput) => void
  componentTypeOptions: { id: number; name: string }[]
  isAutoGraded: boolean
}) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-2 gap-4">
        <FormField label={t('entities.componentType')} htmlFor="assessment_component_type_id" required>
          <Select
            id="assessment_component_type_id"
            value={value.assessment_component_type_id ? String(value.assessment_component_type_id) : undefined}
            onValueChange={(v) => onChange({ ...value, assessment_component_type_id: Number(v) })}
            options={componentTypeOptions.map((ct) => ({ value: String(ct.id), label: ct.name }))}
          />
        </FormField>
        <FormField label={t('exams.maxMarks')} htmlFor="max_marks" required>
          <Input id="max_marks" type="number" min="1" value={value.max_marks} onChange={(e) => onChange({ ...value, max_marks: Number(e.target.value) })} />
        </FormField>
        <FormField label={t('exams.examDate')} htmlFor="exam_date" hint={t('common.optional')}>
          <Input id="exam_date" type="date" value={value.exam_date ?? ''} onChange={(e) => onChange({ ...value, exam_date: e.target.value || null })} />
        </FormField>
      </div>

      {isAutoGraded && (
        <label className="flex items-center gap-2 text-sm">
          <Checkbox checked={value.is_online ?? false} onCheckedChange={(checked) => onChange({ ...value, is_online: checked })} />
          {t('exams.onlineTestCheckboxLabel')}
        </label>
      )}

      {isAutoGraded && value.is_online && (
        <div className="grid grid-cols-2 gap-4 rounded-md border border-border p-3">
          <FormField label={t('exams.durationMinutes')} htmlFor="duration_minutes">
            <Input id="duration_minutes" type="number" min="1" value={value.duration_minutes ?? ''} onChange={(e) => onChange({ ...value, duration_minutes: e.target.value ? Number(e.target.value) : null })} />
          </FormField>
          <FormField label={t('exams.maxAttempts')} htmlFor="max_attempts">
            <Input id="max_attempts" type="number" min="1" max="10" value={value.max_attempts ?? 1} onChange={(e) => onChange({ ...value, max_attempts: Number(e.target.value) })} />
          </FormField>
          <FormField label={t('exams.opensAt')} htmlFor="online_starts_at" hint={t('exams.yourLocalTime')}>
            <Input id="online_starts_at" type="datetime-local" value={toDatetimeLocalInput(value.online_starts_at)} onChange={(e) => onChange({ ...value, online_starts_at: fromDatetimeLocalInput(e.target.value) })} />
          </FormField>
          <FormField label={t('exams.closesAt')} htmlFor="online_ends_at" hint={t('exams.yourLocalTime')}>
            <Input id="online_ends_at" type="datetime-local" value={toDatetimeLocalInput(value.online_ends_at)} onChange={(e) => onChange({ ...value, online_ends_at: fromDatetimeLocalInput(e.target.value) })} />
          </FormField>
          <label className="col-span-2 flex items-center gap-2 text-sm">
            <Checkbox checked={value.shuffle_questions ?? true} onCheckedChange={(checked) => onChange({ ...value, shuffle_questions: checked })} />
            {t('exams.shuffleQuestionsLabel')}
          </label>
        </div>
      )}
    </div>
  )
}
