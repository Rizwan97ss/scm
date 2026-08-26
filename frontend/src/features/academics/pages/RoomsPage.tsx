import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Trash2 } from 'lucide-react'
import { roomsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button, ConfirmDialog, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import { ROOM_TYPES } from '@/types/enums'
import { ROOM_TYPE_TRANSLATION_KEY } from '../enumLabels'
import type { Room, RoomPayload } from '@/types/academic'

const emptyForm: RoomPayload = { name: '', code: '', capacity: null, type: 'classroom' }

export function RoomsPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const { sort, setPage, setSort, queryParams } = usePagination('name', 'name')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(roomsApi, queryKeys.rooms, queryParams, 'Room')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Room | null>(null)
  const [form, setForm] = useState<RoomPayload>(emptyForm)
  const [deleting, setDeleting] = useState<Room | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(emptyForm)
    setModalOpen(true)
  }
  function openEdit(row: Room) {
    setEditing(row)
    setForm({ name: row.name, code: row.code, capacity: row.capacity, type: row.type })
    setModalOpen(true)
  }
  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<Room>[] = [
    { key: 'name', header: t('common.name'), sortable: true, render: (row) => row.name },
    { key: 'code', header: t('common.code'), render: (row) => row.code },
    { key: 'type', header: t('academics.type'), render: (row) => t(ROOM_TYPE_TRANSLATION_KEY[row.type]) },
    { key: 'capacity', header: t('academics.capacity'), render: (row) => row.capacity ?? '—' },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {can('academic-structure.edit') && (
            <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
              {t('common.edit')}
            </Button>
          )}
          {can('academic-structure.delete') && (
            <Button variant="outline" size="sm" onClick={() => setDeleting(row)} aria-label={t('common.deleteItem', { item: row.name })}>
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
        title={t('nav.rooms')}
        description={t('academics.roomsDescription')}
        actions={
          can('academic-structure.create') && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.room') })}
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
        emptyTitle={t('common.noItemsYet', { items: t('nav.rooms') })}
      />
      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? t('common.editItem', { item: t('entities.room') }) : t('common.newItem', { item: t('entities.room') })}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('common.name')} htmlFor="name" required>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label={t('common.code')} htmlFor="code" required>
            <Input id="code" required value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
          </FormField>
          <FormField label={t('academics.type')} htmlFor="type">
            <Select
              id="type"
              value={form.type}
              onValueChange={(value) => setForm({ ...form, type: value as RoomPayload['type'] })}
              options={ROOM_TYPES.map((type) => ({ value: type, label: t(ROOM_TYPE_TRANSLATION_KEY[type]) }))}
            />
          </FormField>
          <FormField label={t('academics.capacity')} htmlFor="capacity">
            <Input
              id="capacity"
              type="number"
              value={form.capacity ?? ''}
              onChange={(e) => setForm({ ...form, capacity: e.target.value ? Number(e.target.value) : null })}
            />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.room') })}
          </Button>
        </form>
      </Modal>
      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('common.deleteConfirmTitle', { name: deleting?.name })}
        description={t('common.cannotBeUndone')}
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />
    </div>
  )
}
