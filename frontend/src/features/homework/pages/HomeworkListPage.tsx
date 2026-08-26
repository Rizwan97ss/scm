import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { homeworkApi } from '@/api/endpoints/homework'
import { classSubjectTeachersApi, sectionsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useAuth } from '@/context/AuthContext'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, Select, Textarea, type DataTableColumn } from '@/components/ui'
import { formatDate } from '@/utils/formatDate'
import { routePaths } from '@/routes/routePaths'
import { HomeworkSubmitModal } from '../components/HomeworkSubmitModal'
import { HOMEWORK_SUBMISSION_STATUS_LABEL_KEYS } from '@/types/homework'
import type { Homework, HomeworkPayload } from '@/types/homework'

const EMPTY_FORM: HomeworkPayload = { academic_year_id: 0, section_id: 0, subject_id: 0, title: '', description: '', due_date: '', max_score: null }

export function HomeworkListPage() {
  const { t } = useTranslation()
  const { user, hasRole } = useAuth()
  const { can } = usePermission()
  const navigate = useNavigate()
  const isStudent = hasRole('Student')
  const { setPage, queryParams } = usePagination('-due_date')
  const { listQuery, createMutation } = useCrudResource(homeworkApi, queryKeys.homework, queryParams, 'Homework')

  const canCreate = can('homework.create')
  const { data: assignments } = useQuery({
    queryKey: ['class-subject-teachers', 'all'],
    queryFn: () => classSubjectTeachersApi.list(),
    enabled: canCreate,
  })
  const { data: sections } = useQuery({
    queryKey: queryKeys.sections({ per_page: 100 }),
    queryFn: () => sectionsApi.list({ per_page: 100 }),
    enabled: canCreate,
  })
  const sectionNameById = useMemo(() => new Map((sections?.data ?? []).map((s) => [s.id, s.name])), [sections])

  // Teacher/Class Teacher may only assign homework to a class they're actually
  // assigned to teach (enforced server-side too — see HomeworkController's
  // assertCanManage); School Admin sees every assignment in the school since
  // they aren't restricted to any one of them.
  const myAssignments = useMemo(() => {
    if (!assignments) return []
    return hasRole('Teacher', 'Class Teacher') ? assignments.filter((a) => a.teacher.id === user?.id) : assignments
  }, [assignments, hasRole, user])

  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<HomeworkPayload>(EMPTY_FORM)
  const [submitTarget, setSubmitTarget] = useState<Homework | null>(null)

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<Homework>[] = [
    { key: 'title', header: t('homework.titleLabel'), render: (row) => <span className="font-medium">{row.title}</span> },
    { key: 'subject', header: t('entities.subject'), render: (row) => row.subject?.name ?? '—' },
    { key: 'section', header: t('entities.section'), render: (row) => row.section?.name ?? '—' },
    { key: 'due_date', header: t('homework.dueDateLabel'), render: (row) => formatDate(row.due_date) },
    isStudent
      ? {
          key: 'status',
          header: t('common.status'),
          render: (row) => {
            const status = row.my_submission?.status
            return status ? (
              <Badge variant={status === 'graded' ? 'success' : 'info'}>{t(HOMEWORK_SUBMISSION_STATUS_LABEL_KEYS[status])}</Badge>
            ) : (
              <Badge variant="outline">{t('homework.notSubmittedBadge')}</Badge>
            )
          },
        }
      : { key: 'teacher', header: t('homework.teacherColumn'), render: (row) => row.teacher?.full_name ?? '—' },
  ]

  return (
    <div>
      <PageHeader
        title={t('nav.homework')}
        description={isStudent ? t('homework.descriptionStudent') : t('homework.descriptionStaff')}
        actions={
          canCreate && (
            <Button
              onClick={() => {
                setForm(EMPTY_FORM)
                setModalOpen(true)
              }}
            >
              <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.homework') })}
            </Button>
          )
        }
      />
      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(r) => r.id}
        isLoading={listQuery.isLoading}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        onRowClick={(row) => (isStudent ? setSubmitTarget(row) : navigate(routePaths.homeworkDetail(row.id)))}
        emptyTitle={t('common.noItemsYet', { items: t('nav.homework') })}
        emptyDescription={canCreate ? t('homework.emptyDescription') : undefined}
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={t('common.newItem', { item: t('entities.homework') })}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('homework.classLabel')} htmlFor="assignment" required hint={t('homework.classHint')}>
            <Select
              id="assignment"
              value={form.section_id && form.subject_id ? `${form.section_id}-${form.subject_id}` : undefined}
              onValueChange={(value) => {
                const assignment = myAssignments.find((a) => `${a.section_id}-${a.subject.id}` === value)
                if (assignment) setForm((prev) => ({ ...prev, academic_year_id: assignment.academic_year_id, section_id: assignment.section_id, subject_id: assignment.subject.id }))
              }}
              options={myAssignments.map((a) => ({ value: `${a.section_id}-${a.subject.id}`, label: `${sectionNameById.get(a.section_id) ?? a.section_id} — ${a.subject.name}` }))}
              placeholder={t('homework.selectClassPlaceholder')}
            />
          </FormField>
          <FormField label={t('homework.titleLabel')} htmlFor="title" required>
            <Input id="title" required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
          </FormField>
          <FormField label={t('common.description')} htmlFor="description" hint={t('common.optional')}>
            <Textarea id="description" rows={3} value={form.description ?? ''} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </FormField>
          <FormField label={t('homework.dueDateLabel')} htmlFor="due_date" required>
            <Input id="due_date" type="date" required value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} />
          </FormField>
          <FormField label={t('homework.maxScoreLabel')} htmlFor="max_score" hint={t('common.optional')}>
            <Input
              id="max_score"
              type="number"
              min="0"
              value={form.max_score ?? ''}
              onChange={(e) => setForm({ ...form, max_score: e.target.value ? Number(e.target.value) : null })}
            />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending} className="mt-2">
            {t('homework.assignHomeworkSubmit')}
          </Button>
        </form>
      </Modal>

      {submitTarget && <HomeworkSubmitModal homework={submitTarget} open={!!submitTarget} onOpenChange={(open) => !open && setSubmitTarget(null)} />}
    </div>
  )
}
