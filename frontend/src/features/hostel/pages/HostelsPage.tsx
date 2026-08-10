import { useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { hostelsApi } from '@/api/endpoints/hostel'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, ConfirmDialog, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import { HOSTEL_TYPE_LABELS, HOSTEL_TYPES } from '@/types/hostel'
import type { Hostel, HostelPayload } from '@/types/hostel'

const EMPTY_FORM: HostelPayload = { name: '', type: 'boys', address: '', warden_name: '', warden_phone: '', is_active: true }

export function HostelsPage() {
  const { can } = usePermission()
  const canManage = can('hostel.manage')
  const { sort, setPage, setSort, queryParams } = usePagination('name', 'name')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(hostelsApi, queryKeys.hostels, queryParams, 'Hostel')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Hostel | null>(null)
  const [form, setForm] = useState<HostelPayload>(EMPTY_FORM)
  const [deleting, setDeleting] = useState<Hostel | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(hostel: Hostel) {
    setEditing(hostel)
    setForm({
      name: hostel.name,
      type: hostel.type,
      address: hostel.address ?? '',
      warden_name: hostel.warden_name ?? '',
      warden_phone: hostel.warden_phone ?? '',
      is_active: hostel.is_active,
    })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<Hostel>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'type', header: 'Type', render: (row) => row.type_label },
    { key: 'warden_name', header: 'Warden', render: (row) => row.warden_name ?? '—' },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? 'Active' : 'Inactive'}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {canManage && (
            <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
              Edit
            </Button>
          )}
          {canManage && (
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
        title="Hostels"
        description="Boarding houses and their wardens."
        actions={
          canManage && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> New Hostel
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
        emptyTitle="No hostels yet"
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Hostel' : 'New Hostel'}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Name" htmlFor="name" required>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Type" htmlFor="type" required>
            <Select
              id="type"
              value={form.type}
              onValueChange={(value) => setForm({ ...form, type: value as HostelPayload['type'] })}
              options={HOSTEL_TYPES.map((t) => ({ value: t, label: HOSTEL_TYPE_LABELS[t] }))}
            />
          </FormField>
          <FormField label="Address" htmlFor="address">
            <Input id="address" value={form.address ?? ''} onChange={(e) => setForm({ ...form, address: e.target.value })} />
          </FormField>
          <div className="grid grid-cols-2 gap-4">
            <FormField label="Warden Name" htmlFor="warden_name">
              <Input id="warden_name" value={form.warden_name ?? ''} onChange={(e) => setForm({ ...form, warden_name: e.target.value })} />
            </FormField>
            <FormField label="Warden Phone" htmlFor="warden_phone">
              <Input id="warden_phone" value={form.warden_phone ?? ''} onChange={(e) => setForm({ ...form, warden_phone: e.target.value })} />
            </FormField>
          </div>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? 'Save changes' : 'Create hostel'}
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
