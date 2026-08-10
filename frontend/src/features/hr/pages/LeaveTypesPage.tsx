import { useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { leaveTypesApi } from '@/api/endpoints/hr'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Checkbox, ConfirmDialog, DataTable, FormField, Input, Modal, Textarea, type DataTableColumn } from '@/components/ui'
import type { LeaveType, LeaveTypePayload } from '@/types/hr'

const EMPTY_FORM: LeaveTypePayload = { name: '', days_allowed_per_year: null, is_paid: true, description: '', is_active: true }

export function LeaveTypesPage() {
  const { can } = usePermission()
  const { sort, setPage, setSort, queryParams } = usePagination('name', 'name')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(leaveTypesApi, queryKeys.leaveTypes, queryParams, 'Leave type')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<LeaveType | null>(null)
  const [form, setForm] = useState<LeaveTypePayload>(EMPTY_FORM)
  const [deleting, setDeleting] = useState<LeaveType | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(leaveType: LeaveType) {
    setEditing(leaveType)
    setForm({
      name: leaveType.name,
      days_allowed_per_year: leaveType.days_allowed_per_year,
      is_paid: leaveType.is_paid,
      description: leaveType.description ?? '',
      is_active: leaveType.is_active,
    })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<LeaveType>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'days', header: 'Days/Year', render: (row) => row.days_allowed_per_year ?? 'Unlimited' },
    { key: 'paid', header: 'Paid', render: (row) => <Badge variant={row.is_paid ? 'success' : 'default'}>{row.is_paid ? 'Paid' : 'Unpaid'}</Badge> },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? 'Active' : 'Inactive'}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {can('leave.manage') && (
            <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
              Edit
            </Button>
          )}
          {can('leave.manage') && (
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
        title="Leave Types"
        description="Categories staff request leave under (Casual, Sick, Earned, ...)."
        actions={
          can('leave.manage') && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> New Leave Type
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
        emptyTitle="No leave types yet"
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Leave Type' : 'New Leave Type'}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Name" htmlFor="name" required>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Days allowed per year" htmlFor="days_allowed_per_year" hint="Leave blank for unlimited">
            <Input
              id="days_allowed_per_year"
              type="number"
              min="1"
              value={form.days_allowed_per_year ?? ''}
              onChange={(e) => setForm({ ...form, days_allowed_per_year: e.target.value ? Number(e.target.value) : null })}
            />
          </FormField>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={form.is_paid ?? true} onCheckedChange={(checked) => setForm({ ...form, is_paid: checked })} />
            Paid leave
          </label>
          <FormField label="Description" htmlFor="description" hint="Optional">
            <Textarea id="description" value={form.description ?? ''} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? 'Save changes' : 'Create leave type'}
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
