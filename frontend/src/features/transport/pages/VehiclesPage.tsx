import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Trash2 } from 'lucide-react'
import { vehiclesApi } from '@/api/endpoints/transport'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { useDebounce } from '@/hooks/useDebounce'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, ConfirmDialog, DataTable, FormField, Input, Modal, SearchInput, type DataTableColumn } from '@/components/ui'
import type { Vehicle, VehiclePayload } from '@/types/transport'

const EMPTY_FORM: VehiclePayload = { registration_number: '', capacity: 1, driver_name: '', driver_phone: '', is_active: true }

export function VehiclesPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const canManage = can('transport.manage')
  const { sort, search, setPage, setSort, setSearch, queryParams } = usePagination('registration_number', 'registration_number')
  const debouncedSearch = useDebounce(search)
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(
    vehiclesApi,
    queryKeys.vehicles,
    { ...queryParams, 'filter[registration_number]': debouncedSearch || undefined },
    'Vehicle'
  )

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Vehicle | null>(null)
  const [form, setForm] = useState<VehiclePayload>(EMPTY_FORM)
  const [deleting, setDeleting] = useState<Vehicle | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(vehicle: Vehicle) {
    setEditing(vehicle)
    setForm({
      registration_number: vehicle.registration_number,
      capacity: vehicle.capacity,
      driver_name: vehicle.driver_name ?? '',
      driver_phone: vehicle.driver_phone ?? '',
      is_active: vehicle.is_active,
    })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<Vehicle>[] = [
    { key: 'registration_number', header: t('transport.registrationNumber'), sortable: true, render: (row) => <span className="font-medium">{row.registration_number}</span> },
    { key: 'capacity', header: t('transport.capacity'), align: 'right', render: (row) => row.capacity },
    { key: 'driver_name', header: t('transport.driver'), render: (row) => row.driver_name ?? '—' },
    { key: 'driver_phone', header: t('common.phone'), render: (row) => row.driver_phone ?? '—' },
    { key: 'status', header: t('common.status'), render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? t('common.active') : t('common.inactive')}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {canManage && (
            <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
              {t('common.edit')}
            </Button>
          )}
          {canManage && (
            <Button variant="outline" size="sm" onClick={() => setDeleting(row)} aria-label={t('common.deleteItem', { item: row.registration_number })}>
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
        title={t('nav.vehicles')}
        description={t('transport.vehiclesDescription')}
        actions={
          canManage && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.vehicle') })}
            </Button>
          )
        }
      />

      <div className="mb-4 max-w-sm">
        <SearchInput placeholder={t('transport.vehiclesSearchPlaceholder')} value={search} onChange={setSearch} />
      </div>

      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(row) => row.id}
        isLoading={listQuery.isLoading} isError={listQuery.isError} onRetry={listQuery.refetch}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        sort={sort}
        onSortChange={setSort}
        emptyTitle={debouncedSearch ? t('transport.vehiclesEmptyTitleSearch', { query: debouncedSearch }) : t('common.noItemsYet', { items: t('nav.vehicles') })}
        emptyDescription={debouncedSearch ? t('transport.vehiclesEmptyDescriptionSearch') : undefined}
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? t('common.editItem', { item: t('entities.vehicle') }) : t('common.newItem', { item: t('entities.vehicle') })}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('transport.registrationNumberFull')} htmlFor="registration_number" required>
            <Input id="registration_number" required value={form.registration_number} onChange={(e) => setForm({ ...form, registration_number: e.target.value })} />
          </FormField>
          <FormField label={t('transport.capacity')} htmlFor="capacity" required>
            <Input id="capacity" type="number" min={1} required value={form.capacity} onChange={(e) => setForm({ ...form, capacity: Number(e.target.value) })} />
          </FormField>
          <div className="grid grid-cols-2 gap-4">
            <FormField label={t('transport.driverName')} htmlFor="driver_name">
              <Input id="driver_name" value={form.driver_name ?? ''} onChange={(e) => setForm({ ...form, driver_name: e.target.value })} />
            </FormField>
            <FormField label={t('transport.driverPhone')} htmlFor="driver_phone">
              <Input id="driver_phone" value={form.driver_phone ?? ''} onChange={(e) => setForm({ ...form, driver_phone: e.target.value })} />
            </FormField>
          </div>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.vehicle') })}
          </Button>
        </form>
      </Modal>

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('common.deleteConfirmTitle', { name: deleting?.registration_number })}
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
