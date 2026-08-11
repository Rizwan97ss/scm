import { useState } from 'react'
import { Plus, CheckCircle2 } from 'lucide-react'
import { academicYearsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, Switch, type DataTableColumn } from '@/components/ui'
import type { AcademicYear, AcademicYearPayload } from '@/types/academic'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

const emptyForm: AcademicYearPayload = { name: '', start_date: '', end_date: '', status: 'upcoming' }

export function AcademicYearsPage() {
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
      toast.success('Academic year activated.')
      queryClient.invalidateQueries({ queryKey: queryKeys.academicYears() })
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
    { key: 'name', header: 'Name', sortable: true, render: (row) => row.name },
    { key: 'start_date', header: 'Start', sortable: true, render: (row) => row.start_date },
    { key: 'end_date', header: 'End', render: (row) => row.end_date },
    {
      key: 'status',
      header: 'Status',
      render: (row) => (
        <div className="flex items-center gap-2">
          <Badge variant={row.status === 'active' ? 'success' : row.status === 'closed' ? 'default' : 'info'}>{row.status}</Badge>
          {row.is_current && <Badge variant="primary">Current</Badge>}
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
              <CheckCircle2 className="h-3.5 w-3.5" /> Activate
            </Button>
          )}
          {can('academic-years.edit') && (
            <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
              Edit
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title="Academic Years"
        description="Manage the school's academic years and mark which one is current."
        actions={
          can('academic-years.create') && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> New Academic Year
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
        emptyTitle="No academic years yet"
        emptyDescription="Create your first academic year to start setting up classes and timetables."
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Academic Year' : 'New Academic Year'}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Name" htmlFor="name" required hint='e.g. "2026-2027"'>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <div className="grid grid-cols-2 gap-4">
            <FormField label="Start date" htmlFor="start_date" required>
              <Input id="start_date" type="date" required value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} />
            </FormField>
            <FormField label="End date" htmlFor="end_date" required>
              <Input id="end_date" type="date" required value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} />
            </FormField>
          </div>
          <label className="flex items-center gap-2 text-sm">
            <Switch checked={!!form.is_current} onCheckedChange={(checked) => setForm({ ...form, is_current: checked })} />
            Mark as current academic year
          </label>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? 'Save changes' : 'Create academic year'}
          </Button>
        </form>
      </Modal>
    </div>
  )
}
