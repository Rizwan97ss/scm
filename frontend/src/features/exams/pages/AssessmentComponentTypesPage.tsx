import { useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { assessmentComponentTypesApi } from '@/api/endpoints/exams'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { Badge, Button, Checkbox, ConfirmDialog, DataTable, FormField, Input, Modal, type DataTableColumn } from '@/components/ui'
import type { AssessmentComponentType, AssessmentComponentTypePayload } from '@/types/exam'

const EMPTY_FORM: AssessmentComponentTypePayload = { name: '', code: '', is_auto_graded: false, sequence: 0, is_active: true }

/** Online MCQ / Written / Practical / Oral, etc. — the gradable components a subject's result can combine, configurable per school. */
export function AssessmentComponentTypesPage() {
  const { can } = usePermission()
  const { sort, setPage, setSort, queryParams } = usePagination('sequence', 'sequence')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(assessmentComponentTypesApi, queryKeys.assessmentComponentTypes, queryParams, 'Component type')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<AssessmentComponentType | null>(null)
  const [form, setForm] = useState<AssessmentComponentTypePayload>(EMPTY_FORM)
  const [deleting, setDeleting] = useState<AssessmentComponentType | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(componentType: AssessmentComponentType) {
    setEditing(componentType)
    setForm({ name: componentType.name, code: componentType.code, is_auto_graded: componentType.is_auto_graded, sequence: componentType.sequence, is_active: componentType.is_active })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<AssessmentComponentType>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'code', header: 'Code', render: (row) => <span className="font-mono text-xs text-muted-foreground">{row.code}</span> },
    { key: 'is_auto_graded', header: 'Grading', render: (row) => (row.is_auto_graded ? <Badge variant="info">Auto-graded</Badge> : <Badge variant="outline">Manual entry</Badge>) },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? 'Active' : 'Inactive'}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {can('grading.manage') && <Button variant="outline" size="sm" onClick={() => openEdit(row)}>Edit</Button>}
          {can('grading.manage') && (
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
      <div className="mb-4 flex justify-end">
        {can('grading.manage') && <Button onClick={openCreate}><Plus className="h-4 w-4" /> New Component Type</Button>}
      </div>

      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(row) => row.id}
        isLoading={listQuery.isLoading}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        sort={sort}
        onSortChange={setSort}
        emptyTitle="No component types yet"
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Component Type' : 'New Component Type'}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Name" htmlFor="name" required hint='e.g. "Practical", "Oral / Viva"'>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Code" htmlFor="code" required hint="Short, unique identifier">
            <Input id="code" required value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
          </FormField>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={form.is_auto_graded ?? false} onCheckedChange={(checked) => setForm({ ...form, is_auto_graded: checked })} />
            Auto-graded (online MCQ tests) — shows the online-test scheduling fields when adding this component to an exam
          </label>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? 'Save changes' : 'Create component type'}
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
