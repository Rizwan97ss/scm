import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
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
  const navigate = useNavigate()
  const { data: statement, isLoading } = useQuery({ queryKey: queryKeys.feeStatement(studentId), queryFn: () => invoicesApi.statement(studentId) })

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

      {statement.invoices.length > 0 && (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Invoice #</TableHead>
              <TableHead>Issued</TableHead>
              <TableHead>Due</TableHead>
              <TableHead className="text-right">Total</TableHead>
              <TableHead className="text-right">Balance</TableHead>
              <TableHead>Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {statement.invoices.map((invoice) => (
              <TableRow key={invoice.id} className="cursor-pointer" onClick={() => navigate(routePaths.invoiceDetail(invoice.id))}>
                <TableCell className="font-medium">{invoice.invoice_number}</TableCell>
                <TableCell>{formatDate(invoice.issue_date)}</TableCell>
                <TableCell>{formatDate(invoice.due_date)}</TableCell>
                <TableCell className="text-right">{formatCurrency(invoice.total)}</TableCell>
                <TableCell className="text-right">{formatCurrency(invoice.balance)}</TableCell>
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
