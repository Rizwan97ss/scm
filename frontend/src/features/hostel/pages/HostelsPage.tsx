import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Trash2 } from 'lucide-react'
import { hostelsApi } from '@/api/endpoints/hostel'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { useDebounce } from '@/hooks/useDebounce'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, ConfirmDialog, DataTable, FormField, Input, Modal, SearchInput, Select, type DataTableColumn } from '@/components/ui'
import { HOSTEL_TYPE_LABEL_KEYS, HOSTEL_TYPES } from '@/types/hostel'
import type { Hostel, HostelPayload } from '@/types/hostel'

const EMPTY_FORM: HostelPayload = { name: '', type: 'boys', address: '', warden_name: '', warden_phone: '', is_active: true }

export function HostelsPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const canManage = can('hostel.manage')
  const { sort, search, setPage, setSort, setSearch, queryParams } = usePagination('name', 'name')
  const debouncedSearch = useDebounce(search)
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(
    hostelsApi,
    queryKeys.hostels,
    { ...queryParams, 'filter[name]': debouncedSearch || undefined },
    'Hostel'
  )

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
    { key: 'name', header: t('common.name'), sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'type', header: t('hostel.typeLabel'), render: (row) => row.type_label },
    { key: 'warden_name', header: t('hostel.wardenColumn'), render: (row) => row.warden_name ?? '—' },
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
        title={t('nav.hostels')}
        description={t('hostel.hostelsDescription')}
        actions={
          canManage && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.hostel') })}
            </Button>
          )
        }
      />

      <div className="mb-4 max-w-sm">
        <SearchInput placeholder={t('hostel.hostelsSearchPlaceholder')} value={search} onChange={setSearch} />
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
        emptyTitle={debouncedSearch ? t('hostel.hostelsEmptyTitleSearch', { query: debouncedSearch }) : t('common.noItemsYet', { items: t('nav.hostels') })}
        emptyDescription={debouncedSearch ? t('hostel.hostelsEmptyDescriptionSearch') : undefined}
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? t('common.editItem', { item: t('entities.hostel') }) : t('common.newItem', { item: t('entities.hostel') })}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('common.name')} htmlFor="name" required>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label={t('hostel.typeLabel')} htmlFor="type" required>
            <Select
              id="type"
              value={form.type}
              onValueChange={(value) => setForm({ ...form, type: value as HostelPayload['type'] })}
              options={HOSTEL_TYPES.map((type) => ({ value: type, label: t(HOSTEL_TYPE_LABEL_KEYS[type]) }))}
            />
          </FormField>
          <FormField label={t('common.address')} htmlFor="address">
            <Input id="address" value={form.address ?? ''} onChange={(e) => setForm({ ...form, address: e.target.value })} />
          </FormField>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label={t('hostel.wardenNameLabel')} htmlFor="warden_name">
              <Input id="warden_name" value={form.warden_name ?? ''} onChange={(e) => setForm({ ...form, warden_name: e.target.value })} />
            </FormField>
            <FormField label={t('hostel.wardenPhoneLabel')} htmlFor="warden_phone">
              <Input id="warden_phone" value={form.warden_phone ?? ''} onChange={(e) => setForm({ ...form, warden_phone: e.target.value })} />
            </FormField>
          </div>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.hostel') })}
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
