import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Download, Zap } from 'lucide-react'
import { payslipsApi } from '@/api/endpoints/hr'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Modal, Select, type DataTableColumn } from '@/components/ui'
import { formatCurrency } from '@/utils/formatCurrency'
import type { Payslip } from '@/types/hr'
import type { ApiError } from '@/api/client'

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'default'> = {
  generated: 'warning',
  paid: 'success',
}

const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

export function PayslipsPage() {
  const { can } = usePermission()
  const canManage = can('payroll.manage')
  const canViewAll = can('payroll.view') || canManage
  const { setPage, queryParams } = usePagination('-year')
  const listQuery = useQuery({ queryKey: queryKeys.payslips(queryParams), queryFn: () => payslipsApi.list(queryParams) })
  const queryClient = useQueryClient()

  const [generateModalOpen, setGenerateModalOpen] = useState(false)

  const markPaidMutation = useMutation({
    mutationFn: (id: number) => payslipsApi.markPaid(id),
    onSuccess: () => {
      toast.success('Payslip marked paid.')
      queryClient.invalidateQueries({ queryKey: queryKeys.payslips().slice(0, 1) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const columns: DataTableColumn<Payslip>[] = [
    ...(canViewAll ? [{ key: 'user', header: 'Staff Member', render: (row: Payslip) => row.user?.full_name ?? '—' } satisfies DataTableColumn<Payslip>] : []),
    { key: 'payslip_number', header: 'Payslip #', render: (row) => row.payslip_number },
    { key: 'period', header: 'Period', render: (row) => `${MONTH_NAMES[row.month - 1]} ${row.year}` },
    { key: 'net', header: 'Net Salary', align: 'right', render: (row) => <span className="font-medium">{formatCurrency(row.net_salary)}</span> },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={STATUS_VARIANT[row.status] ?? 'default'}>{row.status_label}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          <a
            href={payslipsApi.receiptPdfUrl(row.id)}
            target="_blank"
            rel="noopener"
            className="flex items-center gap-1 text-sm text-primary hover:underline"
            aria-label={`Download payslip ${row.payslip_number}`}
          >
            <Download className="h-3.5 w-3.5" /> PDF
          </a>
          {canManage && row.status === 'generated' && (
            <Button
              variant="outline"
              size="sm"
              isLoading={markPaidMutation.isPending && markPaidMutation.variables === row.id}
              onClick={() => markPaidMutation.mutate(row.id)}
            >
              Mark Paid
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title="Payslips"
        description={canViewAll ? 'Every staff member’s payslips.' : 'Your payslips.'}
        actions={
          canManage && (
            <Button onClick={() => setGenerateModalOpen(true)}>
              <Zap className="h-4 w-4" /> Generate Payroll
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
        emptyTitle="No payslips yet"
      />

      {generateModalOpen && <GeneratePayrollModal open={generateModalOpen} onOpenChange={setGenerateModalOpen} />}
    </div>
  )
}

function GeneratePayrollModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const now = new Date()
  const [month, setMonth] = useState(now.getMonth() + 1)
  const [year, setYear] = useState(now.getFullYear())

  const mutation = useMutation({
    mutationFn: () => payslipsApi.generate({ month, year }),
    onSuccess: (result) => {
      toast.success(`Generated ${result.created_count} payslip(s); skipped ${result.skipped_count} already-paid staff member(s).`)
      queryClient.invalidateQueries({ queryKey: queryKeys.payslips().slice(0, 1) })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title="Generate Payroll">
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <p className="text-sm text-muted-foreground">
          Creates one payslip per staff member with an active salary structure, snapshotting their current pay. Staff already paid for this period are
          skipped automatically.
        </p>
        <div className="grid grid-cols-2 gap-4">
          <FormField label="Month" htmlFor="month" required>
            <Select
              id="month"
              value={String(month)}
              onValueChange={(value) => setMonth(Number(value))}
              options={MONTH_NAMES.map((name, index) => ({ value: String(index + 1), label: name }))}
            />
          </FormField>
          <FormField label="Year" htmlFor="year" required>
            <Select
              id="year"
              value={String(year)}
              onValueChange={(value) => setYear(Number(value))}
              options={[year - 1, year, year + 1].map((y) => ({ value: String(y), label: String(y) }))}
            />
          </FormField>
        </div>
        <Button type="submit" isLoading={mutation.isPending} className="mt-2">
          Generate payroll
        </Button>
      </form>
    </Modal>
  )
}
