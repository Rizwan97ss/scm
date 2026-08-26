import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Trash2 } from 'lucide-react'
import { examTypesApi } from '@/api/endpoints/exams'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { Badge, Button, ConfirmDialog, DataTable, FormField, Input, Modal, type DataTableColumn } from '@/components/ui'
import type { ExamType, ExamTypePayload } from '@/types/exam'

const EMPTY_FORM: ExamTypePayload = { name: '', code: '', sequence: 0, is_active: true }

/** Class Test / Unit Test / Trimester / Semester / Final, etc. — configurable per school, seeded with a canonical starting set at provisioning. */
export function ExamTypesPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const { sort, setPage, setSort, queryParams } = usePagination('sequence', 'sequence')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(examTypesApi, queryKeys.examTypes, queryParams, 'Exam type')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<ExamType | null>(null)
  const [form, setForm] = useState<ExamTypePayload>(EMPTY_FORM)
  const [deleting, setDeleting] = useState<ExamType | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(examType: ExamType) {
    setEditing(examType)
    setForm({ name: examType.name, code: examType.code, sequence: examType.sequence, is_active: examType.is_active })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<ExamType>[] = [
    { key: 'name', header: t('common.name'), sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'code', header: t('common.code'), render: (row) => <span className="font-mono text-xs text-muted-foreground">{row.code}</span> },
    { key: 'status', header: t('common.status'), render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? t('common.active') : t('common.inactive')}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {can('grading.manage') && <Button variant="outline" size="sm" onClick={() => openEdit(row)}>{t('common.edit')}</Button>}
          {can('grading.manage') && (
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
      <div className="mb-4 flex justify-end">
        {can('grading.manage') && <Button onClick={openCreate}><Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.examType') })}</Button>}
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
        emptyTitle={t('common.noItemsYet', { items: t('exams.examTypes') })}
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? t('common.editItem', { item: t('entities.examType') }) : t('common.newItem', { item: t('entities.examType') })}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('common.name')} htmlFor="name" required hint={t('exams.examTypeNameHint')}>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label={t('common.code')} htmlFor="code" required hint={t('exams.codeHint')}>
            <Input id="code" required value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.examType') })}
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
