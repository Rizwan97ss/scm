import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, CheckCircle2 } from 'lucide-react'
import { academicYearsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, Switch, type DataTableColumn } from '@/components/ui'
import type { AcademicYear, AcademicYearPayload } from '@/types/academic'
import type { AcademicYearStatus } from '@/types/enums'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

const emptyForm: AcademicYearPayload = { name: '', start_date: '', end_date: '', status: 'upcoming' }

const STATUS_TRANSLATION_KEY: Record<AcademicYearStatus, string> = {
  upcoming: 'academics.statusUpcoming',
  active: 'academics.statusActive',
  closed: 'academics.statusClosed',
}

export function AcademicYearsPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const { sort, setPage, setSort, queryParams } = usePagination('-start_date')
  const { listQuery, createMutation, updateMutation } = useCrudResource(
    academicYearsApi,
    queryKeys.academicYears,
    queryParams,
    'Academic year'
  )
  const queryClient = useQueryClient()
  const activateMutation = useMutation({
    mutationFn: academicYearsApi.activate,
    onSuccess: () => {
      toast.success(t('academics.yearActivatedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.academicYears().slice(0, 1) })
    },
  })

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<AcademicYear | null>(null)
  const [form, setForm] = useState<AcademicYearPayload>(emptyForm)

  function openCreate() {
    setEditing(null)
    setForm(emptyForm)
    setModalOpen(true)
  }

  function openEdit(year: AcademicYear) {
    setEditing(year)
    setForm({ name: year.name, start_date: year.start_date, end_date: year.end_date, is_current: year.is_current, status: year.status })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) {
      await updateMutation.mutateAsync({ id: editing.id, payload: form })
    } else {
      await createMutation.mutateAsync(form)
    }
    setModalOpen(false)
  }

  const columns: DataTableColumn<AcademicYear>[] = [
    { key: 'name', header: t('common.name'), sortable: true, render: (row) => row.name },
    { key: 'start_date', header: t('academics.start'), sortable: true, render: (row) => row.start_date },
    { key: 'end_date', header: t('academics.end'), render: (row) => row.end_date },
    {
      key: 'status',
      header: t('common.status'),
      render: (row) => (
        <div className="flex items-center gap-2">
          <Badge variant={row.status === 'active' ? 'success' : row.status === 'closed' ? 'default' : 'info'}>{t(STATUS_TRANSLATION_KEY[row.status])}</Badge>
          {row.is_current && <Badge variant="primary">{t('academics.current')}</Badge>}
        </div>
      ),
    },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {!row.is_current && can('academic-years.edit') && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => activateMutation.mutate(row.id)}
              isLoading={activateMutation.isPending && activateMutation.variables === row.id}
            >
              <CheckCircle2 className="h-3.5 w-3.5" /> {t('academics.activate')}
            </Button>
          )}
          {can('academic-years.edit') && (
            <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
              {t('common.edit')}
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title={t('nav.academic_years')}
        description={t('academics.academicYearsDescription')}
        actions={
          can('academic-years.create') && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.academicYear') })}
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
        emptyTitle={t('common.noItemsYet', { items: t('nav.academic_years') })}
        emptyDescription={t('academics.academicYearsEmptyDescription')}
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? t('common.editItem', { item: t('entities.academicYear') }) : t('common.newItem', { item: t('entities.academicYear') })}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('common.name')} htmlFor="name" required hint={t('academics.academicYearNameHint')}>
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
          <label className="flex items-center gap-2 text-sm">
            <Switch checked={!!form.is_current} onCheckedChange={(checked) => setForm({ ...form, is_current: checked })} />
            {t('academics.markAsCurrentYear')}
          </label>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.academicYear') })}
          </Button>
        </form>
      </Modal>
    </div>
  )
}
