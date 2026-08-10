import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Download, Plus } from 'lucide-react'
import { invoicesApi, paymentsApi } from '@/api/endpoints/fees'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import {
  Badge,
  Button,
  Card,
  CardContent,
  ConfirmDialog,
  FormField,
  Input,
  Modal,
  Select,
  Skeleton,
  StatCard,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  Textarea,
} from '@/components/ui'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate } from '@/utils/formatDate'
import { routePaths } from '@/routes/routePaths'
import { PAYMENT_METHODS, PAYMENT_METHOD_LABELS } from '@/types/fees'
import type { IssueCreditNotePayload, PaymentMethod, RecordPaymentPayload } from '@/types/fees'
import type { ApiError } from '@/api/client'

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'destructive' | 'default' | 'info'> = {
  draft: 'default',
  issued: 'info',
  partially_paid: 'warning',
  paid: 'success',
  void: 'destructive',
}

export function InvoiceDetailPage() {
  const { id } = useParams<{ id: string }>()
  const invoiceId = Number(id)
  const { can } = usePermission()
  const queryClient = useQueryClient()

  const { data: invoice, isLoading } = useQuery({ queryKey: queryKeys.invoice(invoiceId), queryFn: () => invoicesApi.get(invoiceId) })

  const [paymentModalOpen, setPaymentModalOpen] = useState(false)
  const [creditNoteModalOpen, setCreditNoteModalOpen] = useState(false)
  const [voidConfirmOpen, setVoidConfirmOpen] = useState(false)

  const voidMutation = useMutation({
    mutationFn: () => invoicesApi.void(invoiceId),
    onSuccess: () => {
      toast.success('Invoice voided.')
      queryClient.invalidateQueries({ queryKey: queryKeys.invoice(invoiceId) })
      setVoidConfirmOpen(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  if (isLoading || !invoice) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  const canRecordPayment = can('invoices.record-payment') && invoice.status !== 'void' && invoice.balance > 0
  const canIssueCreditNote = can('invoices.issue-credit-note') && invoice.status !== 'void' && invoice.balance > 0
  const canVoid = can('invoices.void') && invoice.status !== 'void' && invoice.amount_paid === 0

  return (
    <div>
      <PageHeader
        title={invoice.invoice_number}
        description={`${invoice.student?.full_name ?? ''} · Issued ${formatDate(invoice.issue_date)} · Due ${formatDate(invoice.due_date)}`}
        breadcrumbs={[{ label: 'Invoices', to: routePaths.invoices }, { label: invoice.invoice_number }]}
        actions={
          <div className="flex gap-2">
            {canRecordPayment && (
              <Button onClick={() => setPaymentModalOpen(true)}>
                <Plus className="h-4 w-4" /> Record Payment
              </Button>
            )}
            {canIssueCreditNote && (
              <Button variant="outline" onClick={() => setCreditNoteModalOpen(true)}>
                Issue Credit Note
              </Button>
            )}
            {canVoid && (
              <Button variant="outline" onClick={() => setVoidConfirmOpen(true)}>
                Void
              </Button>
            )}
          </div>
        }
      />

      <div className="mb-6 flex items-center gap-2">
        <Badge variant={STATUS_VARIANT[invoice.status] ?? 'default'}>{invoice.status_label}</Badge>
        {invoice.is_overdue && <Badge variant="destructive">Overdue</Badge>}
      </div>

      <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <StatCard label="Total" value={formatCurrency(invoice.total)} />
        <StatCard label="Paid" value={formatCurrency(invoice.amount_paid)} />
        <StatCard label="Credited" value={formatCurrency(invoice.credit_total)} />
        <StatCard label="Balance" value={formatCurrency(invoice.balance)} />
      </div>

      <Card className="mb-6">
        <CardContent className="pt-6">
          <h3 className="mb-3 text-sm font-semibold">Line Items</h3>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Description</TableHead>
                <TableHead>Category</TableHead>
                <TableHead className="text-right">Qty</TableHead>
                <TableHead className="text-right">Unit Amount</TableHead>
                <TableHead className="text-right">Amount</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {invoice.items.map((item) => (
                <TableRow key={item.id}>
                  <TableCell>{item.description}</TableCell>
                  <TableCell className="text-muted-foreground">{item.fee_category?.name ?? '—'}</TableCell>
                  <TableCell className="text-right">{item.quantity}</TableCell>
                  <TableCell className="text-right">{formatCurrency(item.unit_amount)}</TableCell>
                  <TableCell className="text-right">{formatCurrency(item.amount)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          {invoice.discount_total > 0 && (
            <p className="mt-2 text-right text-sm text-muted-foreground">Subtotal {formatCurrency(invoice.subtotal)} − Discount {formatCurrency(invoice.discount_total)}</p>
          )}
          {invoice.notes && <p className="mt-3 text-sm text-muted-foreground">{invoice.notes}</p>}
        </CardContent>
      </Card>

      <Card className="mb-6">
        <CardContent className="pt-6">
          <h3 className="mb-3 text-sm font-semibold">Payments</h3>
          {invoice.payments.length === 0 && <p className="text-sm text-muted-foreground">No payments recorded yet.</p>}
          {invoice.payments.length > 0 && (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Receipt #</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead>Method</TableHead>
                  <TableHead>Reference</TableHead>
                  <TableHead className="text-right">Amount</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {invoice.payments.map((payment) => (
                  <TableRow key={payment.id}>
                    <TableCell>{payment.payment_number}</TableCell>
                    <TableCell>{formatDate(payment.paid_at)}</TableCell>
                    <TableCell>{payment.method_label}</TableCell>
                    <TableCell className="text-muted-foreground">{payment.reference_number ?? '—'}</TableCell>
                    <TableCell className="text-right">{formatCurrency(payment.amount)}</TableCell>
                    <TableCell>
                      <a
                        href={paymentsApi.receiptPdfUrl(payment.id)}
                        target="_blank"
                        rel="noreferrer"
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
        </CardContent>
      </Card>

      {invoice.credit_notes.length > 0 && (
        <Card className="mb-6">
          <CardContent className="pt-6">
            <h3 className="mb-3 text-sm font-semibold">Credit Notes</h3>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Credit Note #</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead>Reason</TableHead>
                  <TableHead className="text-right">Amount</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {invoice.credit_notes.map((creditNote) => (
                  <TableRow key={creditNote.id}>
                    <TableCell>{creditNote.credit_note_number}</TableCell>
                    <TableCell>{formatDate(creditNote.issued_at)}</TableCell>
                    <TableCell className="text-muted-foreground">{creditNote.reason}</TableCell>
                    <TableCell className="text-right">{formatCurrency(creditNote.amount)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      {paymentModalOpen && (
        <RecordPaymentModal invoiceId={invoiceId} balance={invoice.balance} open={paymentModalOpen} onOpenChange={setPaymentModalOpen} />
      )}
      {creditNoteModalOpen && (
        <IssueCreditNoteModal invoiceId={invoiceId} balance={invoice.balance} open={creditNoteModalOpen} onOpenChange={setCreditNoteModalOpen} />
      )}

      <ConfirmDialog
        open={voidConfirmOpen}
        onOpenChange={setVoidConfirmOpen}
        title="Void this invoice?"
        description="A voided invoice can no longer accept payments or credit notes. This cannot be undone."
        confirmLabel="Void invoice"
        isLoading={voidMutation.isPending}
        onConfirm={() => voidMutation.mutate()}
      />
    </div>
  )
}

function RecordPaymentModal({ invoiceId, balance, open, onOpenChange }: { invoiceId: number; balance: number; open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const today = new Date().toISOString().slice(0, 10)
  const [form, setForm] = useState<RecordPaymentPayload>({ amount: balance, method: 'cash', reference_number: '', paid_at: today, notes: '' })

  const mutation = useMutation({
    mutationFn: () => invoicesApi.recordPayment(invoiceId, form),
    onSuccess: () => {
      toast.success('Payment recorded.')
      queryClient.invalidateQueries({ queryKey: queryKeys.invoice(invoiceId) })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title="Record Payment">
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label="Amount" htmlFor="amount" required hint={`Outstanding balance: ${formatCurrency(balance)}`}>
          <Input id="amount" type="number" min="0.01" max={balance} step="0.01" required value={form.amount || ''} onChange={(e) => setForm({ ...form, amount: Number(e.target.value) })} />
        </FormField>
        <FormField label="Method" htmlFor="method" required>
          <Select
            id="method"
            value={form.method}
            onValueChange={(value) => setForm({ ...form, method: value as PaymentMethod })}
            options={PAYMENT_METHODS.map((m) => ({ value: m, label: PAYMENT_METHOD_LABELS[m] }))}
          />
        </FormField>
        <FormField label="Reference #" htmlFor="reference_number" hint="Cheque number, transaction ID, etc. — optional">
          <Input id="reference_number" value={form.reference_number ?? ''} onChange={(e) => setForm({ ...form, reference_number: e.target.value })} />
        </FormField>
        <FormField label="Date Paid" htmlFor="paid_at" required>
          <Input id="paid_at" type="date" required value={form.paid_at} onChange={(e) => setForm({ ...form, paid_at: e.target.value })} />
        </FormField>
        <FormField label="Notes" htmlFor="notes" hint="Optional">
          <Textarea id="notes" rows={2} value={form.notes ?? ''} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
        </FormField>
        <Button type="submit" isLoading={mutation.isPending} className="mt-2">
          Record payment
        </Button>
      </form>
    </Modal>
  )
}

function IssueCreditNoteModal({ invoiceId, balance, open, onOpenChange }: { invoiceId: number; balance: number; open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<IssueCreditNotePayload>({ amount: balance, reason: '' })

  const mutation = useMutation({
    mutationFn: () => invoicesApi.issueCreditNote(invoiceId, form),
    onSuccess: () => {
      toast.success('Credit note issued.')
      queryClient.invalidateQueries({ queryKey: queryKeys.invoice(invoiceId) })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title="Issue Credit Note">
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label="Amount" htmlFor="cn_amount" required hint={`Outstanding balance: ${formatCurrency(balance)}`}>
          <Input id="cn_amount" type="number" min="0.01" max={balance} step="0.01" required value={form.amount || ''} onChange={(e) => setForm({ ...form, amount: Number(e.target.value) })} />
        </FormField>
        <FormField label="Reason" htmlFor="reason" required>
          <Textarea id="reason" rows={3} required value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} />
        </FormField>
        <Button type="submit" isLoading={mutation.isPending} className="mt-2">
          Issue credit note
        </Button>
      </form>
    </Modal>
  )
}
