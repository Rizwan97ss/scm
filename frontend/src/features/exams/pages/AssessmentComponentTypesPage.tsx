import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Trash2 } from 'lucide-react'
import { assessmentComponentTypesApi } from '@/api/endpoints/exams'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { useDebounce } from '@/hooks/useDebounce'
import { Badge, Button, Checkbox, ConfirmDialog, DataTable, FormField, Input, Modal, SearchInput, type DataTableColumn } from '@/components/ui'
import type { AssessmentComponentType, AssessmentComponentTypePayload } from '@/types/exam'

const EMPTY_FORM: AssessmentComponentTypePayload = { name: '', code: '', is_auto_graded: false, sequence: 0, is_active: true }

/** Online MCQ / Written / Practical / Oral, etc. — the gradable components a subject's result can combine, configurable per school. */
export function AssessmentComponentTypesPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const { sort, search, setPage, setSort, setSearch, queryParams } = usePagination('sequence', 'name')
  const debouncedSearch = useDebounce(search)
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(
    assessmentComponentTypesApi,
    queryKeys.assessmentComponentTypes,
    { ...queryParams, 'filter[name]': debouncedSearch || undefined },
    'Component type'
  )

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
    { key: 'name', header: t('common.name'), sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'code', header: t('common.code'), render: (row) => <span className="font-mono text-xs text-muted-foreground">{row.code}</span> },
    { key: 'is_auto_graded', header: t('exams.grading'), render: (row) => (row.is_auto_graded ? <Badge variant="info">{t('exams.autoGraded')}</Badge> : <Badge variant="outline">{t('exams.manualEntry')}</Badge>) },
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
      <div className="mb-4 flex items-center justify-between gap-4">
        <div className="max-w-sm flex-1">
          <SearchInput placeholder={t('common.searchByName')} value={search} onChange={setSearch} />
        </div>
        {can('grading.manage') && <Button onClick={openCreate}><Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.componentType') })}</Button>}
      </div>

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
        emptyTitle={debouncedSearch ? t('exams.componentTypesNoMatchTitle', { query: debouncedSearch }) : t('common.noItemsYet', { items: t('exams.componentTypes') })}
        emptyDescription={debouncedSearch ? t('exams.componentTypesNoMatchDescription') : undefined}
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? t('common.editItem', { item: t('entities.componentType') }) : t('common.newItem', { item: t('entities.componentType') })}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('common.name')} htmlFor="name" required hint={t('exams.componentTypeNameHint')}>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label={t('common.code')} htmlFor="code" required hint={t('exams.componentTypeCodeHint')}>
            <Input id="code" required value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
          </FormField>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={form.is_auto_graded ?? false} onCheckedChange={(checked) => setForm({ ...form, is_auto_graded: checked })} />
            {t('exams.autoGradedHint')}
          </label>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.componentType') })}
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
