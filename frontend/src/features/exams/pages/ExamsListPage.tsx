import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { examsApi } from '@/api/endpoints/exams'
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
  const { can } = usePermission()
  const navigate = useNavigate()
  const { setPage, queryParams } = usePagination('-created_at')
  const { listQuery, createMutation } = useCrudResource(examsApi, queryKeys.exams, queryParams, 'Exam')
  const { data: academicYears } = useQuery({ queryKey: queryKeys.academicYears({ per_page: 100 }), queryFn: () => academicYearsApi.list({ per_page: 100 }) })
  const { data: terms } = useQuery({ queryKey: queryKeys.terms({ per_page: 100 }), queryFn: () => termsApi.list({ per_page: 100 }) })

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
    { key: 'name', header: 'Name', sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'subjects', header: 'Subjects', render: (row) => row.exam_subjects.length },
    { key: 'weight', header: 'Weight', render: (row) => row.weight },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={row.is_published ? 'success' : 'default'}>{row.is_published ? 'Published' : 'Draft'}</Badge> },
  ]

  return (
    <div>
      <PageHeader
        title="Exams"
        description="Create exams, configure subjects, enter marks, and publish results."
        actions={can('exams.create') && <Button onClick={openCreate}><Plus className="h-4 w-4" /> New Exam</Button>}
      />
      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(r) => r.id}
        isLoading={listQuery.isLoading}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        onRowClick={(row) => navigate(routePaths.examDetail(row.id))}
        emptyTitle="No exams yet"
        emptyDescription={can('exams.create') ? 'Create your first exam to get started.' : undefined}
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title="New Exam" description="Add subjects and configure marks after creating the exam.">
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Name" htmlFor="name" required hint='e.g. "Midterm Exam"'>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Academic Year" htmlFor="academic_year_id" required>
            <Select
              id="academic_year_id"
              value={form.academic_year_id ? String(form.academic_year_id) : undefined}
              onValueChange={(value) => setForm({ ...form, academic_year_id: Number(value) })}
              options={(academicYears?.data ?? []).map((y) => ({ value: String(y.id), label: y.name }))}
            />
          </FormField>
          <FormField label="Term" htmlFor="term_id" hint="Optional — needed for the consolidated term result">
            <Select
              id="term_id"
              value={form.term_id ? String(form.term_id) : undefined}
              onValueChange={(value) => setForm({ ...form, term_id: Number(value) })}
              options={(terms?.data ?? []).map((t) => ({ value: String(t.id), label: t.name }))}
              placeholder="None"
            />
          </FormField>
          <FormField label="Weight" htmlFor="weight" hint="Relative contribution toward the term result (e.g. Midterm 1, Final 2)">
            <Input id="weight" type="number" step="0.01" min="0.01" value={form.weight ?? 1} onChange={(e) => setForm({ ...form, weight: Number(e.target.value) })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending} className="mt-2">Create exam</Button>
        </form>
      </Modal>
    </div>
  )
}
