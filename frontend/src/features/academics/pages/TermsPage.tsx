import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { academicYearsApi, termsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import type { Term, TermPayload } from '@/types/academic'

export function TermsPage() {
  const { can } = usePermission()
  const { sort, setPage, setSort, queryParams } = usePagination('sequence')
  const { listQuery, createMutation, updateMutation } = useCrudResource(termsApi, queryKeys.terms, queryParams, 'Term')
  const { data: academicYears } = useQuery({ queryKey: queryKeys.academicYears({ per_page: 100 }), queryFn: () => academicYearsApi.list({ per_page: 100 }) })

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Term | null>(null)
  const [form, setForm] = useState<TermPayload>({ academic_year_id: 0, name: '', start_date: '', end_date: '', sequence: 1 })

  function openCreate() {
    setEditing(null)
    setForm({ academic_year_id: academicYears?.data[0]?.id ?? 0, name: '', start_date: '', end_date: '', sequence: 1 })
    setModalOpen(true)
  }

  function openEdit(term: Term) {
    setEditing(term)
    setForm({ academic_year_id: term.academic_year_id, name: term.name, start_date: term.start_date, end_date: term.end_date, sequence: term.sequence })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) {
      await updateMutation.mutateAsync({ id: editing.id, payload: form })
    } else {
      await createMutation.mutateAsync(form)
    }
    setModalOpen(false)
  }

  const yearName = (id: number) => academicYears?.data.find((year) => year.id === id)?.name ?? '—'

  const columns: DataTableColumn<Term>[] = [
    { key: 'name', header: 'Name', render: (row) => row.name },
    { key: 'academic_year', header: 'Academic Year', render: (row) => yearName(row.academic_year_id) },
    { key: 'start_date', header: 'Start', render: (row) => row.start_date },
    { key: 'end_date', header: 'End', render: (row) => row.end_date },
    { key: 'is_current', header: 'Status', render: (row) => (row.is_current ? <Badge variant="primary">Current</Badge> : null) },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) =>
        can('academic-years.edit') && (
          <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
            Edit
          </Button>
        ),
    },
  ]

  return (
    <div>
      <PageHeader
        title="Terms"
        description="Split each academic year into terms or semesters."
        actions={
          can('academic-years.create') && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> New Term
            </Button>
          )
        }
      />

      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(row) => row.id}
        isLoading={listQuery.isLoading}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        sort={sort}
        onSortChange={setSort}
        emptyTitle="No terms yet"
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Term' : 'New Term'}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Academic Year" htmlFor="academic_year_id" required>
            <Select
              id="academic_year_id"
              value={String(form.academic_year_id)}
              onValueChange={(value) => setForm({ ...form, academic_year_id: Number(value) })}
              options={(academicYears?.data ?? []).map((year) => ({ value: String(year.id), label: year.name }))}
            />
          </FormField>
          <FormField label="Name" htmlFor="name" required hint='e.g. "Term 1"'>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <div className="grid grid-cols-2 gap-4">
            <FormField label="Start date" htmlFor="start_date" required>
              <Input id="start_date" type="date" required value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} />
            </FormField>
            <FormField label="End date" htmlFor="end_date" required>
              <Input id="end_date" type="date" required value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} />
            </FormField>
          </div>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? 'Save changes' : 'Create term'}
          </Button>
        </form>
      </Modal>
    </div>
  )
}
