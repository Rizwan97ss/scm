import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
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

export function PayslipsPage() {
  const { t } = useTranslation()
  const monthNames = t('common.months', { returnObjects: true }) as string[]
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
      toast.success(t('hr.markPaidToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.payslips().slice(0, 1) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const columns: DataTableColumn<Payslip>[] = [
    ...(canViewAll ? [{ key: 'user', header: t('library.staffMember'), render: (row: Payslip) => row.user?.full_name ?? '—' } satisfies DataTableColumn<Payslip>] : []),
    { key: 'payslip_number', header: t('hr.payslipNumberColumn'), render: (row) => row.payslip_number },
    { key: 'period', header: t('hr.periodColumn'), render: (row) => `${monthNames[row.month - 1]} ${row.year}` },
    { key: 'net', header: t('hr.netSalaryLabel'), align: 'right', render: (row) => <span className="font-medium">{formatCurrency(row.net_salary)}</span> },
    { key: 'status', header: t('common.status'), render: (row) => <Badge variant={STATUS_VARIANT[row.status] ?? 'default'}>{row.status_label}</Badge> },
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
            aria-label={t('hr.downloadPayslipAria', { number: row.payslip_number })}
          >
            <Download className="h-3.5 w-3.5" /> {t('hr.pdfLink')}
          </a>
          {canManage && row.status === 'generated' && (
            <Button
              variant="outline"
              size="sm"
              isLoading={markPaidMutation.isPending && markPaidMutation.variables === row.id}
              onClick={() => markPaidMutation.mutate(row.id)}
            >
              {t('hr.markPaidAction')}
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title={t('nav.payslips')}
        description={canViewAll ? t('hr.payslipsDescriptionAll') : t('hr.payslipsDescriptionOwn')}
        actions={
          canManage && (
            <Button onClick={() => setGenerateModalOpen(true)}>
              <Zap className="h-4 w-4" /> {t('hr.generatePayroll')}
            </Button>
          )
        }
      />

      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(row) => row.id}
        isLoading={listQuery.isLoading} isError={listQuery.isError} onRetry={listQuery.refetch}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        emptyTitle={t('common.noItemsYet', { items: t('nav.payslips') })}
        emptyDescription={t('hr.payslipsEmptyDescription')}
      />

      {generateModalOpen && <GeneratePayrollModal open={generateModalOpen} onOpenChange={setGenerateModalOpen} />}
    </div>
  )
}

function GeneratePayrollModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const { t } = useTranslation()
  const monthNames = t('common.months', { returnObjects: true }) as string[]
  const queryClient = useQueryClient()
  const now = new Date()
  const [month, setMonth] = useState(now.getMonth() + 1)
  const [year, setYear] = useState(now.getFullYear())

  const mutation = useMutation({
    mutationFn: () => payslipsApi.generate({ month, year }),
    onSuccess: (result) => {
      toast.success(t('hr.generatedPayslipsToast', { created: result.created_count, skipped: result.skipped_count }))
      queryClient.invalidateQueries({ queryKey: queryKeys.payslips().slice(0, 1) })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={t('hr.generatePayroll')}>
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <p className="text-sm text-muted-foreground">{t('hr.generatePayrollDescription')}</p>
        <div className="grid grid-cols-2 gap-4">
          <FormField label={t('hr.monthLabel')} htmlFor="month" required>
            <Select
              id="month"
              value={String(month)}
              onValueChange={(value) => setMonth(Number(value))}
              options={monthNames.map((name, index) => ({ value: String(index + 1), label: name }))}
            />
          </FormField>
          <FormField label={t('hr.yearLabel')} htmlFor="year" required>
            <Select
              id="year"
              value={String(year)}
              onValueChange={(value) => setYear(Number(value))}
              options={[year - 1, year, year + 1].map((y) => ({ value: String(y), label: String(y) }))}
            />
          </FormField>
        </div>
        <Button type="submit" isLoading={mutation.isPending} className="mt-2">
          {t('hr.generatePayrollSubmit')}
        </Button>
      </form>
    </Modal>
  )
}
