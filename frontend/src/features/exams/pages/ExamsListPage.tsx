import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { examsApi, examTypesApi } from '@/api/endpoints/exams'
import { academicYearsApi, termsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import type { Exam, ExamPayload } from '@/types/exam'

export function ExamsListPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const navigate = useNavigate()
  const { setPage, queryParams } = usePagination('-created_at')
  const { listQuery, createMutation } = useCrudResource(examsApi, queryKeys.exams, queryParams, 'Exam')
  const { data: academicYears } = useQuery({ queryKey: queryKeys.academicYears({ per_page: 100 }), queryFn: () => academicYearsApi.list({ per_page: 100 }) })
  const { data: terms } = useQuery({ queryKey: queryKeys.terms({ per_page: 100 }), queryFn: () => termsApi.list({ per_page: 100 }) })
  const { data: examTypes } = useQuery({ queryKey: queryKeys.examTypes(), queryFn: () => examTypesApi.list() })

  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<ExamPayload>({ academic_year_id: 0, name: '', weight: 1 })

  function openCreate() {
    setForm({ academic_year_id: academicYears?.data[0]?.id ?? 0, name: '', weight: 1 })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    const exam = await createMutation.mutateAsync(form)
    setModalOpen(false)
    navigate(routePaths.examDetail(exam.id))
  }

  const columns: DataTableColumn<Exam>[] = [
    { key: 'name', header: t('common.name'), sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'exam_type', header: t('exams.type'), render: (row) => row.exam_type?.name ?? '—' },
    {
      key: 'sections',
      header: t('entities.section'),
      render: (row) => {
        const names = [...new Set(row.exam_subject_groups.map((g) => g.section?.name).filter((name): name is string => !!name))]
        return names.length > 0 ? names.join(', ') : '—'
      },
    },
    { key: 'subjects', header: t('nav.subjects'), render: (row) => row.exam_subject_groups.length },
    { key: 'weight', header: t('exams.weight'), render: (row) => row.weight },
    { key: 'status', header: t('common.status'), render: (row) => <Badge variant={row.is_published ? 'success' : 'default'}>{row.is_published ? t('exams.published') : t('exams.draft')}</Badge> },
  ]

  return (
    <div>
      <PageHeader
        title={t('nav.exams')}
        description={t('exams.examsPageDescription')}
        actions={can('exams.create') && <Button onClick={openCreate}><Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.exam') })}</Button>}
      />
      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(r) => r.id}
        isLoading={listQuery.isLoading}
        isError={listQuery.isError}
        onRetry={listQuery.refetch}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        onRowClick={(row) => navigate(routePaths.examDetail(row.id))}
        emptyTitle={t('common.noItemsYet', { items: t('nav.exams') })}
        emptyDescription={can('exams.create') ? t('common.createFirstToGetStarted', { item: t('entities.exam') }) : undefined}
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={t('common.newItem', { item: t('entities.exam') })} description={t('exams.newExamModalDescription')}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('common.name')} htmlFor="name" required hint={t('exams.examNameHint')}>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label={t('entities.academicYear')} htmlFor="academic_year_id" required>
            <Select
              id="academic_year_id"
              value={form.academic_year_id ? String(form.academic_year_id) : undefined}
              onValueChange={(value) => setForm({ ...form, academic_year_id: Number(value) })}
              options={(academicYears?.data ?? []).map((y) => ({ value: String(y.id), label: y.name }))}
            />
          </FormField>
          <FormField label={t('entities.term')} htmlFor="term_id" hint={t('exams.termHint')}>
            <Select
              id="term_id"
              value={form.term_id ? String(form.term_id) : undefined}
              onValueChange={(value) => setForm({ ...form, term_id: Number(value) })}
              options={(terms?.data ?? []).map((term) => ({ value: String(term.id), label: term.name }))}
              placeholder={t('common.none')}
            />
          </FormField>
          <FormField label={t('entities.examType')} htmlFor="exam_type_id" hint={t('exams.examTypeHint')}>
            <Select
              id="exam_type_id"
              value={form.exam_type_id ? String(form.exam_type_id) : undefined}
              onValueChange={(value) => setForm({ ...form, exam_type_id: Number(value) })}
              options={(examTypes?.data ?? []).map((examType) => ({ value: String(examType.id), label: examType.name }))}
              placeholder={t('common.none')}
            />
          </FormField>
          <FormField label={t('exams.weight')} htmlFor="weight" hint={t('exams.weightHint')}>
            <Input id="weight" type="number" step="0.01" min="0.01" value={form.weight ?? 1} onChange={(e) => setForm({ ...form, weight: Number(e.target.value) })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending} className="mt-2">{t('common.createItem', { item: t('entities.exam') })}</Button>
        </form>
      </Modal>
    </div>
  )
}
