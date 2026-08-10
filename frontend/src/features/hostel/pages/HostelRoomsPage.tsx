import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { hostelRoomsApi, hostelsApi } from '@/api/endpoints/hostel'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, ConfirmDialog, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import type { HostelRoom, HostelRoomPayload } from '@/types/hostel'

const EMPTY_FORM: HostelRoomPayload = { hostel_id: 0, room_number: '', capacity: 1, is_active: true }

export function HostelRoomsPage() {
  const { can } = usePermission()
  const canManage = can('hostel.manage')
  const { sort, setPage, setSort, queryParams } = usePagination('room_number', 'room_number')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(hostelRoomsApi, queryKeys.hostelRooms, queryParams, 'Room')
  const { data: hostels } = useQuery({ queryKey: queryKeys.hostels({ per_page: 100 }), queryFn: () => hostelsApi.list({ per_page: 100 }) })

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<HostelRoom | null>(null)
  const [form, setForm] = useState<HostelRoomPayload>(EMPTY_FORM)
  const [deleting, setDeleting] = useState<HostelRoom | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(room: HostelRoom) {
    setEditing(room)
    setForm({ hostel_id: room.hostel?.id ?? 0, room_number: room.room_number, capacity: room.capacity, is_active: room.is_active })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<HostelRoom>[] = [
    { key: 'room_number', header: 'Room #', sortable: true, render: (row) => <span className="font-medium">{row.room_number}</span> },
    { key: 'hostel', header: 'Hostel', render: (row) => row.hostel?.name ?? '—' },
    { key: 'occupancy', header: 'Occupancy', align: 'right', render: (row) => `${row.occupied_count} / ${row.capacity}` },
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
            <Button variant="outline" size="sm" onClick={() => setDeleting(row)} aria-label={`Delete ${row.room_number}`}>
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
        title="Hostel Rooms"
        description="Rooms across every hostel and their capacity."
        actions={
          canManage && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> New Room
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
        emptyTitle="No rooms yet"
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Room' : 'New Room'}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Hostel" htmlFor="hostel_id" required>
            <Select
              id="hostel_id"
              value={form.hostel_id ? String(form.hostel_id) : undefined}
              onValueChange={(value) => setForm({ ...form, hostel_id: Number(value) })}
              options={(hostels?.data ?? []).map((h) => ({ value: String(h.id), label: h.name }))}
              placeholder="Select a hostel"
            />
          </FormField>
          <FormField label="Room Number" htmlFor="room_number" required>
            <Input id="room_number" required value={form.room_number} onChange={(e) => setForm({ ...form, room_number: e.target.value })} />
          </FormField>
          <FormField label="Capacity" htmlFor="capacity" required>
            <Input id="capacity" type="number" min={1} required value={form.capacity} onChange={(e) => setForm({ ...form, capacity: Number(e.target.value) })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} disabled={!form.hostel_id} className="mt-2">
            {editing ? 'Save changes' : 'Create room'}
          </Button>
        </form>
      </Modal>

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={`Delete ${deleting?.room_number}?`}
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
