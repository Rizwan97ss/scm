import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { departmentsApi, subjectsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Checkbox, ConfirmDialog, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import type { Subject, SubjectPayload } from '@/types/academic'

const emptyForm: SubjectPayload = { name: '', code: '', department_id: null, is_elective: false }

export function SubjectsPage() {
  const { can } = usePermission()
  const { sort, setPage, setSort, queryParams } = usePagination('name', 'name')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(subjectsApi, queryKeys.subjects, queryParams, 'Subject')
  const { data: departments } = useQuery({ queryKey: queryKeys.departments({ per_page: 100 }), queryFn: () => departmentsApi.list({ per_page: 100 }) })

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Subject | null>(null)
  const [form, setForm] = useState<SubjectPayload>(emptyForm)
  const [deleting, setDeleting] = useState<Subject | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(emptyForm)
    setModalOpen(true)
  }
  function openEdit(row: Subject) {
    setEditing(row)
    setForm({ name: row.name, code: row.code, department_id: row.department_id, is_elective: row.is_elective })
    setModalOpen(true)
  }
  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<Subject>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => row.name },
    { key: 'code', header: 'Code', render: (row) => row.code },
    { key: 'department', header: 'Department', render: (row) => row.department?.name ?? '—' },
    { key: 'is_elective', header: 'Elective', render: (row) => (row.is_elective ? <Badge variant="info">Elective</Badge> : null) },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {can('academic-structure.edit') && (
            <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
              Edit
            </Button>
          )}
          {can('academic-structure.delete') && (
            <Button variant="outline" size="sm" onClick={() => setDeleting(row)} aria-label={`Delete ${row.name}`}>
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
        title="Subjects"
        description="Subjects taught across the school, optionally grouped by department."
        actions={
          can('academic-structure.create') && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> New Subject
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
        emptyTitle="No subjects yet"
      />
      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Subject' : 'New Subject'}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Name" htmlFor="name" required>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Code" htmlFor="code" required>
            <Input id="code" required value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
          </FormField>
          <FormField label="Department" htmlFor="department_id" hint="Optional">
            <Select
              id="department_id"
              value={form.department_id ? String(form.department_id) : undefined}
              onValueChange={(value) => setForm({ ...form, department_id: value ? Number(value) : null })}
              options={(departments?.data ?? []).map((department) => ({ value: String(department.id), label: department.name }))}
              placeholder="No department"
            />
          </FormField>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={!!form.is_elective} onCheckedChange={(checked) => setForm({ ...form, is_elective: checked })} />
            This is an elective subject
          </label>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? 'Save changes' : 'Create subject'}
          </Button>
        </form>
      </Modal>
      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="This cannot be undone."
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />
    </div>
  )
}
