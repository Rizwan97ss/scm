import { useState } from 'react'
import { Plus } from 'lucide-react'
import { schoolsApi } from '@/api/endpoints/schools'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, type DataTableColumn } from '@/components/ui'
import type { School, SchoolPayload } from '@/types/school'

const emptyForm: SchoolPayload = { name: '', short_name: '' }

export function SchoolsListPage() {
  const { sort, setPage, setSort, queryParams } = usePagination('name', 'name')
  const { listQuery, createMutation } = useCrudResource(schoolsApi, queryKeys.schools, queryParams, 'School')

  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<SchoolPayload>(emptyForm)

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<School>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => row.name },
    { key: 'short_name', header: 'Short name', render: (row) => row.short_name },
    { key: 'city', header: 'City', render: (row) => row.city ?? '—' },
    { key: 'is_active', header: 'Status', render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? 'Active' : 'Inactive'}</Badge> },
  ]

  return (
    <div>
      <PageHeader
        title="Schools"
        description="Every campus/tenant on this platform. Super Admin only."
        actions={
          <Button onClick={() => { setForm(emptyForm); setModalOpen(true) }}>
            <Plus className="h-4 w-4" /> New School
          </Button>
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
        emptyTitle="No schools yet"
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title="New School">
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Name" htmlFor="name" required>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Short name" htmlFor="short_name" required hint="Used to generate a unique slug">
            <Input id="short_name" required value={form.short_name} onChange={(e) => setForm({ ...form, short_name: e.target.value })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending} className="mt-2">
            Create school
          </Button>
        </form>
      </Modal>
    </div>
  )
}
