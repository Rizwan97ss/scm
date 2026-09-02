import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'
import { feeCategoriesApi, invoicesApi } from '@/api/endpoints/fees'
import { academicYearsApi } from '@/api/endpoints/academics'
import { studentsApi } from '@/api/endpoints/students'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate } from '@/utils/formatDate'
import { routePaths } from '@/routes/routePaths'
import type { Invoice, InvoiceItemInput, InvoicePayload } from '@/types/fees'
import type { ApiError } from '@/api/client'

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'destructive' | 'default' | 'info'> = {
  draft: 'default',
  issued: 'info',
  partially_paid: 'warning',
  paid: 'success',
  void: 'destructive',
}

export function InvoicesListPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const navigate = useNavigate()
  const { setPage, queryParams } = usePagination('-issue_date')
  const { listQuery } = useCrudResource(invoicesApi, queryKeys.invoices, queryParams, 'Invoice')

  const [modalOpen, setModalOpen] = useState(false)

  const columns: DataTableColumn<Invoice>[] = [
    { key: 'invoice_number', header: t('fees.invoiceNumber'), render: (row) => <span className="font-medium">{row.invoice_number}</span> },
    { key: 'student', header: t('entities.student'), render: (row) => row.student?.full_name ?? '—' },
    { key: 'issue_date', header: t('fees.issued'), hideBelow: 'md', render: (row) => formatDate(row.issue_date) },
    { key: 'due_date', header: t('fees.dueDate'), render: (row) => formatDate(row.due_date) },
    { key: 'total', header: t('fees.total'), align: 'right', hideBelow: 'md', render: (row) => formatCurrency(row.total) },
    { key: 'balance', header: t('fees.balanceStat'), align: 'right', render: (row) => formatCurrency(row.balance) },
    {
      key: 'status',
      header: t('common.status'),
      render: (row) => (
        <div className="flex items-center gap-1.5">
          <Badge variant={STATUS_VARIANT[row.status] ?? 'default'}>{row.status_label}</Badge>
          {row.is_overdue && <Badge variant="destructive">{t('fees.overdue')}</Badge>}
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title={t('nav.invoices')}
        description={t('fees.invoicesDescription')}
        actions={
          can('invoices.create') && (
            <Button onClick={() => setModalOpen(true)}>
              <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.invoice') })}
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
        onRowClick={(row) => navigate(routePaths.invoiceDetail(row.id))}
        emptyTitle={t('common.noItemsYet', { items: t('nav.invoices') })}
        emptyDescription={can('invoices.create') ? t('fees.createInvoiceHint') : undefined}
      />

      {modalOpen && <CreateInvoiceModal open={modalOpen} onOpenChange={setModalOpen} />}
    </div>
  )
}

const EMPTY_ITEM: InvoiceItemInput = { fee_category_id: 0, description: '', quantity: 1, unit_amount: 0 }

function CreateInvoiceModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const { t } = useTranslation()
  const today = new Date().toISOString().slice(0, 10)
  const queryClient = useQueryClient()

  const { data: students } = useQuery({ queryKey: queryKeys.students({ per_page: 100 }), queryFn: () => studentsApi.list({ per_page: 100 }) })
  const { data: years } = useQuery({ queryKey: queryKeys.academicYears({ per_page: 50 }), queryFn: () => academicYearsApi.list({ per_page: 50 }) })
  const { data: categories } = useQuery({ queryKey: queryKeys.feeCategories({ per_page: 100 }), queryFn: () => feeCategoriesApi.list({ per_page: 100 }) })

  const [form, setForm] = useState<InvoicePayload>({ student_id: 0, academic_year_id: 0, issue_date: today, due_date: today, notes: '', items: [{ ...EMPTY_ITEM }] })
  const [isSubmitting, setIsSubmitting] = useState(false)

  function updateItem(index: number, patch: Partial<InvoiceItemInput>) {
    setForm((prev) => ({ ...prev, items: prev.items.map((item, i) => (i === index ? { ...item, ...patch } : item)) }))
  }

  const total = form.items.reduce((sum, item) => sum + (item.quantity ?? 1) * (item.unit_amount || 0), 0)

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    setIsSubmitting(true)
    try {
      await invoicesApi.create(form)
      toast.success(t('fees.invoiceCreatedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.invoices().slice(0, 1) })
      onOpenChange(false)
    } catch (error) {
      toast.error((error as ApiError).message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={t('common.newItem', { item: t('entities.invoice') })} size="lg">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField label={t('entities.student')} htmlFor="student_id" required>
            <Select
              id="student_id"
              value={form.student_id ? String(form.student_id) : undefined}
              onValueChange={(value) => setForm({ ...form, student_id: Number(value) })}
              options={(students?.data ?? []).map((s) => ({ value: String(s.id), label: `${s.full_name} (${s.admission_number})` }))}
              placeholder={t('fees.selectStudent')}
            />
          </FormField>
          <FormField label={t('entities.academicYear')} htmlFor="academic_year_id" required>
            <Select
              id="academic_year_id"
              value={form.academic_year_id ? String(form.academic_year_id) : undefined}
              onValueChange={(value) => setForm({ ...form, academic_year_id: Number(value) })}
              options={(years?.data ?? []).map((y) => ({ value: String(y.id), label: y.name }))}
              placeholder={t('fees.selectAcademicYear')}
            />
          </FormField>
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <FormField label={t('fees.issueDate')} htmlFor="issue_date" required>
            <Input id="issue_date" type="date" required value={form.issue_date} onChange={(e) => setForm({ ...form, issue_date: e.target.value })} />
          </FormField>
          <FormField label={t('fees.dueDate')} htmlFor="due_date" required>
            <Input id="due_date" type="date" required value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} />
          </FormField>
        </div>

        <div className="flex flex-col gap-2">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold">{t('fees.lineItems')}</h3>
            <Button type="button" variant="outline" size="sm" onClick={() => setForm((prev) => ({ ...prev, items: [...prev.items, { ...EMPTY_ITEM }] }))}>
              <Plus className="h-3.5 w-3.5" /> {t('fees.addItem')}
            </Button>
          </div>
          {form.items.map((item, index) => (
            <div key={index} className="grid grid-cols-1 items-end gap-2 rounded-md border border-border p-2 sm:grid-cols-12">
              <FormField label={t('entities.category')} htmlFor={`item-${index}-category`} className="sm:col-span-3">
                <Select
                  id={`item-${index}-category`}
                  value={item.fee_category_id ? String(item.fee_category_id) : undefined}
                  onValueChange={(value) => updateItem(index, { fee_category_id: Number(value) })}
                  options={(categories?.data ?? []).map((c) => ({ value: String(c.id), label: c.name }))}
                  placeholder={t('entities.category')}
                />
              </FormField>
              <FormField label={t('common.description')} htmlFor={`item-${index}-description`} className="sm:col-span-4">
                <Input id={`item-${index}-description`} required value={item.description} onChange={(e) => updateItem(index, { description: e.target.value })} />
              </FormField>
              <FormField label={t('common.quantity')} htmlFor={`item-${index}-quantity`} className="sm:col-span-2">
                <Input id={`item-${index}-quantity`} type="number" min="1" value={item.quantity ?? 1} onChange={(e) => updateItem(index, { quantity: Number(e.target.value) })} />
              </FormField>
              <FormField label={t('fees.unitAmount')} htmlFor={`item-${index}-unit-amount`} className="sm:col-span-2">
                <Input
                  id={`item-${index}-unit-amount`}
                  type="number"
                  min="0.01"
                  step="0.01"
                  required
                  value={item.unit_amount || ''}
                  onChange={(e) => updateItem(index, { unit_amount: Number(e.target.value) })}
                />
              </FormField>
              <div className="sm:col-span-1">
                <Button
                  type="button"
                  variant="outline"
                  size="icon"
                  onClick={() => setForm((prev) => ({ ...prev, items: prev.items.filter((_, i) => i !== index) }))}
                  disabled={form.items.length <= 1}
                  aria-label={t('fees.removeItemAria')}
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              </div>
            </div>
          ))}
          <p className="text-end text-sm font-medium">
            {t('fees.total')}: {formatCurrency(total)}
          </p>
        </div>

        <Button type="submit" isLoading={isSubmitting} className="mt-2">
          {t('common.createItem', { item: t('entities.invoice') })}
        </Button>
      </form>
    </Modal>
  )
}
