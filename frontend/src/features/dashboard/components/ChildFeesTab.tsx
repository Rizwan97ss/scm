import { useQuery } from '@tanstack/react-query'
import { Download } from 'lucide-react'
import { parentPortalApi } from '@/api/endpoints/dashboard'
import { queryKeys } from '@/api/queryKeys'
import { paymentsApi } from '@/api/endpoints/fees'
import { Badge, EmptyState, Skeleton, StatCard, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate } from '@/utils/formatDate'

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'destructive' | 'default' | 'info'> = {
  draft: 'default',
  issued: 'info',
  partially_paid: 'warning',
  paid: 'success',
  void: 'destructive',
}

export function ChildFeesTab({ studentId }: { studentId: number }) {
  const { data: statement, isLoading } = useQuery({ queryKey: queryKeys.parentChildInvoices(studentId), queryFn: () => parentPortalApi.childInvoices(studentId) })

  if (isLoading) return <Skeleton className="h-48 w-full" />
  if (!statement) return null

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <StatCard label="Total Billed" value={formatCurrency(statement.summary.total_billed)} />
        <StatCard label="Total Paid" value={formatCurrency(statement.summary.total_paid)} />
        <StatCard label="Total Credited" value={formatCurrency(statement.summary.total_credited)} />
        <StatCard label="Outstanding" value={formatCurrency(statement.summary.total_outstanding)} />
      </div>

      {statement.invoices.length === 0 && <EmptyState title="No invoices yet" />}

      {statement.invoices.map((invoice) => (
        <div key={invoice.id} className="rounded-lg border border-border p-4">
          <div className="mb-2 flex items-center justify-between">
            <div>
              <p className="font-medium">{invoice.invoice_number}</p>
              <p className="text-xs text-muted-foreground">
                Issued {formatDate(invoice.issue_date)} · Due {formatDate(invoice.due_date)}
              </p>
            </div>
            <Badge variant={STATUS_VARIANT[invoice.status] ?? 'default'}>{invoice.status_label}</Badge>
          </div>
          <p className="text-sm">
            Total {formatCurrency(invoice.total)} · Paid {formatCurrency(invoice.amount_paid)} · Balance{' '}
            <span className={invoice.balance > 0 ? 'font-semibold text-warning' : ''}>{formatCurrency(invoice.balance)}</span>
          </p>
          {invoice.payments.length > 0 && (
            <Table className="mt-3">
              <TableHeader>
                <TableRow>
                  <TableHead>Receipt #</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead className="text-right">Amount</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {invoice.payments.map((payment) => (
                  <TableRow key={payment.id}>
                    <TableCell>{payment.payment_number}</TableCell>
                    <TableCell>{formatDate(payment.paid_at)}</TableCell>
                    <TableCell className="text-right">{formatCurrency(payment.amount)}</TableCell>
                    <TableCell>
                      <a
                        href={paymentsApi.receiptPdfUrl(payment.id)}
                        target="_blank"
                        rel="noopener"
                        className="flex items-center gap-1 text-sm text-primary hover:underline"
                        aria-label={`Download receipt ${payment.payment_number}`}
                      >
                        <Download className="h-3.5 w-3.5" /> Receipt
                      </a>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </div>
      ))}
    </div>
  )
}
