import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { salaryStructuresApi } from '@/api/endpoints/hr'
import { usersApi } from '@/api/endpoints/users'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate } from '@/utils/formatDate'
import type { SalaryStructure, SalaryStructurePayload } from '@/types/hr'

const EMPTY_FORM: SalaryStructurePayload = { user_id: 0, basic_salary: 0, allowances: 0, deductions: 0, effective_from: new Date().toISOString().slice(0, 10) }

export function SalaryStructuresPage() {
  const { setPage, queryParams } = usePagination('-effective_from')
  const { listQuery, createMutation } = useCrudResource(salaryStructuresApi, queryKeys.salaryStructures, queryParams, 'Salary structure')
  const { data: users } = useQuery({ queryKey: queryKeys.users({ per_page: 200 }), queryFn: () => usersApi.list({ per_page: 200 }) })

  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<SalaryStructurePayload>(EMPTY_FORM)

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    await createMutation.mutateAsync(form)
    setModalOpen(false)
    setForm(EMPTY_FORM)
  }

  const columns: DataTableColumn<SalaryStructure>[] = [
    { key: 'user', header: 'Staff Member', render: (row) => row.user?.full_name ?? '—' },
    { key: 'basic', header: 'Basic', align: 'right', render: (row) => formatCurrency(row.basic_salary) },
    { key: 'allowances', header: 'Allowances', align: 'right', render: (row) => formatCurrency(row.allowances) },
    { key: 'deductions', header: 'Deductions', align: 'right', render: (row) => formatCurrency(row.deductions) },
    { key: 'net', header: 'Net', align: 'right', render: (row) => <span className="font-medium">{formatCurrency(row.net_salary)}</span> },
    { key: 'effective_from', header: 'Effective From', render: (row) => formatDate(row.effective_from) },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? 'Active' : 'Superseded'}</Badge> },
  ]

  return (
    <div>
      <PageHeader
        title="Salary Structures"
        description="Recurring pay components per staff member. Creating a new structure for someone supersedes their previous one."
        actions={
          <Button onClick={() => setModalOpen(true)}>
            <Plus className="h-4 w-4" /> New Structure
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
        emptyTitle="No salary structures yet"
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title="New Salary Structure">
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Staff Member" htmlFor="user_id" required>
            <Select
              id="user_id"
              value={form.user_id ? String(form.user_id) : undefined}
              onValueChange={(value) => setForm({ ...form, user_id: Number(value) })}
              options={(users?.data ?? []).map((u) => ({ value: String(u.id), label: u.full_name }))}
              placeholder="Select a staff member"
            />
          </FormField>
          <div className="grid grid-cols-3 gap-4">
            <FormField label="Basic Salary" htmlFor="basic_salary" required>
              <Input id="basic_salary" type="number" min="0.01" step="0.01" required value={form.basic_salary || ''} onChange={(e) => setForm({ ...form, basic_salary: Number(e.target.value) })} />
            </FormField>
            <FormField label="Allowances" htmlFor="allowances">
              <Input id="allowances" type="number" min="0" step="0.01" value={form.allowances ?? ''} onChange={(e) => setForm({ ...form, allowances: Number(e.target.value) })} />
            </FormField>
            <FormField label="Deductions" htmlFor="deductions">
              <Input id="deductions" type="number" min="0" step="0.01" value={form.deductions ?? ''} onChange={(e) => setForm({ ...form, deductions: Number(e.target.value) })} />
            </FormField>
          </div>
          <FormField label="Effective From" htmlFor="effective_from" required>
            <Input id="effective_from" type="date" required value={form.effective_from} onChange={(e) => setForm({ ...form, effective_from: e.target.value })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending} className="mt-2">
            Create structure
          </Button>
        </form>
      </Modal>
    </div>
  )
}
