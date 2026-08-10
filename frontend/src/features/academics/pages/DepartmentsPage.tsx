import { useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { departmentsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button, ConfirmDialog, DataTable, FormField, Input, Modal, Textarea, type DataTableColumn } from '@/components/ui'
import type { Department, DepartmentPayload } from '@/types/academic'

const emptyForm: DepartmentPayload = { name: '', code: '', description: '' }

export function DepartmentsPage() {
  const { can } = usePermission()
  const { sort, setPage, setSort, queryParams } = usePagination('name', 'name')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(departmentsApi, queryKeys.departments, queryParams, 'Department')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Department | null>(null)
  const [form, setForm] = useState<DepartmentPayload>(emptyForm)
  const [deleting, setDeleting] = useState<Department | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(emptyForm)
    setModalOpen(true)
  }

  function openEdit(department: Department) {
    setEditing(department)
    setForm({ name: department.name, code: department.code, description: department.description ?? '' })
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

  const columns: DataTableColumn<Department>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => row.name },
    { key: 'code', header: 'Code', render: (row) => row.code },
    { key: 'description', header: 'Description', render: (row) => row.description ?? '—' },
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
        title="Departments"
        description="Group subjects by department (e.g. Sciences, Humanities)."
        actions={
          can('academic-structure.create') && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> New Department
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
        emptyTitle="No departments yet"
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Department' : 'New Department'}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Name" htmlFor="name" required>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Code" htmlFor="code" required hint="Short unique code, e.g. SCI">
            <Input id="code" required value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
          </FormField>
          <FormField label="Description" htmlFor="description">
            <Textarea id="description" value={form.description ?? ''} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? 'Save changes' : 'Create department'}
          </Button>
        </form>
      </Modal>

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="This cannot be undone. Subjects linked to this department will lose their department reference."
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />
    </div>
  )
}
