import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Trash2 } from 'lucide-react'
import { gradeLevelsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button, ConfirmDialog, DataTable, FormField, Input, Modal, type DataTableColumn } from '@/components/ui'
import type { GradeLevel, GradeLevelPayload } from '@/types/academic'

const emptyForm: GradeLevelPayload = { name: '', code: '', sequence: 0 }

export function GradeLevelsPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const { sort, setPage, setSort, queryParams } = usePagination('sequence')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(gradeLevelsApi, queryKeys.gradeLevels, queryParams, 'Grade level')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<GradeLevel | null>(null)
  const [form, setForm] = useState<GradeLevelPayload>(emptyForm)
  const [deleting, setDeleting] = useState<GradeLevel | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(emptyForm)
    setModalOpen(true)
  }
  function openEdit(row: GradeLevel) {
    setEditing(row)
    setForm({ name: row.name, code: row.code, sequence: row.sequence })
    setModalOpen(true)
  }
  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<GradeLevel>[] = [
    { key: 'sequence', header: t('academics.order'), sortable: true, render: (row) => row.sequence },
    { key: 'name', header: t('common.name'), render: (row) => row.name },
    { key: 'code', header: t('common.code'), render: (row) => row.code },
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
        title={t('nav.grade_levels')}
        description={t('academics.gradeLevelsDescription')}
        actions={
          can('academic-structure.create') && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.gradeLevel') })}
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
        emptyTitle={t('common.noItemsYet', { items: t('nav.grade_levels') })}
      />
      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? t('common.editItem', { item: t('entities.gradeLevel') }) : t('common.newItem', { item: t('entities.gradeLevel') })}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('common.name')} htmlFor="name" required hint={t('academics.gradeLevelNameHint')}>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label={t('common.code')} htmlFor="code" required hint={t('academics.gradeLevelCodeHint')}>
            <Input id="code" required value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
          </FormField>
          <FormField label={t('academics.sortOrder')} htmlFor="sequence" hint={t('academics.sortOrderHint')}>
            <Input id="sequence" type="number" value={form.sequence ?? 0} onChange={(e) => setForm({ ...form, sequence: Number(e.target.value) })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.gradeLevel') })}
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
