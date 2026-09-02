import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { academicYearsApi, holidaysApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, ConfirmDialog, DataTable, FormField, Input, Modal, Select, Textarea, type DataTableColumn } from '@/components/ui'
import { HOLIDAY_TYPES } from '@/types/enums'
import { HOLIDAY_TYPE_TRANSLATION_KEY } from '../enumLabels'
import type { Holiday, HolidayPayload } from '@/types/academic'

const emptyForm: HolidayPayload = { name: '', start_date: '', end_date: '', type: 'school_specific', description: '' }

export function HolidaysPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const { sort, setPage, setSort, queryParams } = usePagination('start_date')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(holidaysApi, queryKeys.holidays, queryParams, 'Holiday')
  const { data: academicYears } = useQuery({ queryKey: queryKeys.academicYears({ per_page: 100 }), queryFn: () => academicYearsApi.list({ per_page: 100 }) })

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Holiday | null>(null)
  const [form, setForm] = useState<HolidayPayload>(emptyForm)
  const [deleting, setDeleting] = useState<Holiday | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(emptyForm)
    setModalOpen(true)
  }
  function openEdit(row: Holiday) {
    setEditing(row)
    setForm({
      academic_year_id: row.academic_year_id,
      name: row.name,
      start_date: row.start_date,
      end_date: row.end_date,
      type: row.type,
      description: row.description ?? '',
    })
    setModalOpen(true)
  }
  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<Holiday>[] = [
    { key: 'name', header: t('common.name'), render: (row) => row.name },
    { key: 'start_date', header: t('academics.start'), sortable: true, render: (row) => row.start_date },
    { key: 'end_date', header: t('academics.end'), render: (row) => row.end_date },
    { key: 'type', header: t('academics.type'), render: (row) => <Badge variant={row.type === 'public' ? 'info' : 'default'}>{t(HOLIDAY_TYPE_TRANSLATION_KEY[row.type])}</Badge> },
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
        title={t('nav.holidays')}
        description={t('academics.holidaysDescription')}
        actions={
          can('academic-structure.create') && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.holiday') })}
            </Button>
          )
        }
      />
      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(row) => row.id}
        isLoading={listQuery.isLoading}
        isError={listQuery.isError}
        onRetry={listQuery.refetch}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        sort={sort}
        onSortChange={setSort}
        emptyTitle={t('common.noItemsYet', { items: t('nav.holidays') })}
        emptyDescription={t('academics.holidaysEmptyDescription')}
      />
      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? t('common.editItem', { item: t('entities.holiday') }) : t('common.newItem', { item: t('entities.holiday') })}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('common.name')} htmlFor="name" required>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label={t('academics.startDate')} htmlFor="start_date" required>
              <Input id="start_date" type="date" required value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} />
            </FormField>
            <FormField label={t('academics.endDate')} htmlFor="end_date" required>
              <Input id="end_date" type="date" required value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} />
            </FormField>
          </div>
          <FormField label={t('academics.type')} htmlFor="type">
            <Select
              id="type"
              value={form.type}
              onValueChange={(value) => setForm({ ...form, type: value as HolidayPayload['type'] })}
              options={HOLIDAY_TYPES.map((type) => ({ value: type, label: t(HOLIDAY_TYPE_TRANSLATION_KEY[type]) }))}
            />
          </FormField>
          <FormField label={t('entities.academicYear')} htmlFor="academic_year_id" hint={t('common.optional')}>
            <Select
              id="academic_year_id"
              value={form.academic_year_id ? String(form.academic_year_id) : undefined}
              onValueChange={(value) => setForm({ ...form, academic_year_id: value ? Number(value) : null })}
              options={(academicYears?.data ?? []).map((year) => ({ value: String(year.id), label: year.name }))}
              placeholder={t('academics.allYears')}
            />
          </FormField>
          <FormField label={t('common.description')} htmlFor="description">
            <Textarea id="description" value={form.description ?? ''} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.holiday') })}
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
