import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { invoicesApi } from '@/api/endpoints/fees'
import { queryKeys } from '@/api/queryKeys'
import { Badge, EmptyState, Skeleton, StatCard, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate } from '@/utils/formatDate'
import { routePaths } from '@/routes/routePaths'

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'destructive' | 'default' | 'info'> = {
  draft: 'default',
  issued: 'info',
  partially_paid: 'warning',
  paid: 'success',
  void: 'destructive',
}

export function StudentFeesTab({ studentId }: { studentId: number }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data: statement, isLoading } = useQuery({ queryKey: queryKeys.feeStatement(studentId), queryFn: () => invoicesApi.statement(studentId) })

  if (isLoading) return <Skeleton className="h-48 w-full" />
  if (!statement) return null

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <StatCard label={t('fees.totalBilled')} value={formatCurrency(statement.summary.total_billed)} />
        <StatCard label={t('fees.totalPaidLabel')} value={formatCurrency(statement.summary.total_paid)} />
        <StatCard label={t('fees.totalCreditedLabel')} value={formatCurrency(statement.summary.total_credited)} />
        <StatCard label={t('fees.outstandingLabel')} value={formatCurrency(statement.summary.total_outstanding)} />
      </div>

      {statement.invoices.length === 0 && <EmptyState title={t('common.noItemsYet', { items: t('nav.invoices') })} />}

      {statement.invoices.length > 0 && (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('fees.invoiceNumber')}</TableHead>
              <TableHead>{t('fees.issued')}</TableHead>
              <TableHead>{t('fees.dueDate')}</TableHead>
              <TableHead className="text-end">{t('fees.total')}</TableHead>
              <TableHead className="text-end">{t('fees.balanceStat')}</TableHead>
              <TableHead>{t('common.status')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {statement.invoices.map((invoice) => (
              <TableRow key={invoice.id} className="cursor-pointer" onClick={() => navigate(routePaths.invoiceDetail(invoice.id))}>
                <TableCell className="font-medium">{invoice.invoice_number}</TableCell>
                <TableCell>{formatDate(invoice.issue_date)}</TableCell>
                <TableCell>{formatDate(invoice.due_date)}</TableCell>
                <TableCell className="text-end">{formatCurrency(invoice.total)}</TableCell>
                <TableCell className="text-end">{formatCurrency(invoice.balance)}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[invoice.status] ?? 'default'}>{invoice.status_label}</Badge>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
  )
}
