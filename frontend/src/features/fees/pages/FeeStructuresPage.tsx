import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2, Zap } from 'lucide-react'
import { feeCategoriesApi, feeStructuresApi } from '@/api/endpoints/fees'
import { academicYearsApi, gradeLevelsApi, sectionsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Checkbox, ConfirmDialog, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import { formatCurrency } from '@/utils/formatCurrency'
import { FEE_FREQUENCIES, FEE_FREQUENCY_LABELS } from '@/types/fees'
import type { FeeStructure, FeeStructurePayload, GenerateInvoicesPayload } from '@/types/fees'
import type { ApiError } from '@/api/client'

const EMPTY_FORM: FeeStructurePayload = { academic_year_id: 0, grade_level_id: null, fee_category_id: 0, name: '', amount: 0, frequency: 'annual', due_day_of_month: null, is_active: true }

export function FeeStructuresPage() {
  const { can } = usePermission()
  const { setPage, queryParams } = usePagination('name')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(feeStructuresApi, queryKeys.feeStructures, queryParams, 'Fee structure')

  const { data: years } = useQuery({ queryKey: queryKeys.academicYears({ per_page: 50 }), queryFn: () => academicYearsApi.list({ per_page: 50 }) })
  const { data: gradeLevels } = useQuery({ queryKey: queryKeys.gradeLevels({ per_page: 100 }), queryFn: () => gradeLevelsApi.list({ per_page: 100 }) })
  const { data: categories } = useQuery({ queryKey: queryKeys.feeCategories({ per_page: 100 }), queryFn: () => feeCategoriesApi.list({ per_page: 100 }) })
  const { data: sections } = useQuery({ queryKey: queryKeys.sections({ per_page: 200 }), queryFn: () => sectionsApi.list({ per_page: 200 }) })

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<FeeStructure | null>(null)
  const [form, setForm] = useState<FeeStructurePayload>(EMPTY_FORM)
  const [deleting, setDeleting] = useState<FeeStructure | null>(null)
  const [generateTarget, setGenerateTarget] = useState<FeeStructure | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(structure: FeeStructure) {
    setEditing(structure)
    setForm({
      academic_year_id: structure.academic_year_id,
      grade_level_id: structure.grade_level?.id ?? null,
      fee_category_id: structure.fee_category.id,
      name: structure.name,
      amount: structure.amount,
      frequency: structure.frequency,
      due_day_of_month: structure.due_day_of_month,
      is_active: structure.is_active,
    })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<FeeStructure>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'category', header: 'Category', render: (row) => row.fee_category.name },
    { key: 'grade', header: 'Grade', render: (row) => row.grade_level?.name ?? 'All grades' },
    { key: 'amount', header: 'Amount', align: 'right', render: (row) => formatCurrency(row.amount) },
    { key: 'frequency', header: 'Frequency', render: (row) => row.frequency_label },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? 'Active' : 'Inactive'}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {can('invoices.create') && (
            <Button variant="outline" size="sm" onClick={() => setGenerateTarget(row)}>
              <Zap className="h-3.5 w-3.5" /> Generate invoices
            </Button>
          )}
          {can('fees.edit') && (
            <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
              Edit
            </Button>
          )}
          {can('fees.delete') && (
            <Button variant="outline" size="sm" onClick={() => setDeleting(row)} aria-label={`Delete ${row.name}`}>
              <Trash2 className="h-3.5 w-3.5" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title="Fee Structures"
        description="Fee templates by grade level and frequency — the basis for bulk-generating student invoices."
        actions={
          can('fees.create') && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> New Structure
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
        emptyTitle="No fee structures yet"
        emptyDescription={can('fees.create') ? 'Create a fee structure to start billing students.' : undefined}
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Fee Structure' : 'New Fee Structure'}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Name" htmlFor="name" required>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Academic Year" htmlFor="academic_year_id" required>
            <Select
              id="academic_year_id"
              value={form.academic_year_id ? String(form.academic_year_id) : undefined}
              onValueChange={(value) => setForm({ ...form, academic_year_id: Number(value) })}
              options={(years?.data ?? []).map((y) => ({ value: String(y.id), label: y.name }))}
              placeholder="Select academic year"
            />
          </FormField>
          <FormField label="Grade Level" htmlFor="grade_level_id" hint="Leave unset to apply to every grade">
            <Select
              id="grade_level_id"
              value={form.grade_level_id ? String(form.grade_level_id) : 'all'}
              onValueChange={(value) => setForm({ ...form, grade_level_id: value === 'all' ? null : Number(value) })}
              options={[{ value: 'all', label: 'All grades' }, ...(gradeLevels?.data ?? []).map((g) => ({ value: String(g.id), label: g.name }))]}
            />
          </FormField>
          <FormField label="Fee Category" htmlFor="fee_category_id" required>
            <Select
              id="fee_category_id"
              value={form.fee_category_id ? String(form.fee_category_id) : undefined}
              onValueChange={(value) => setForm({ ...form, fee_category_id: Number(value) })}
              options={(categories?.data ?? []).map((c) => ({ value: String(c.id), label: c.name }))}
              placeholder="Select fee category"
            />
          </FormField>
          <div className="grid grid-cols-2 gap-4">
            <FormField label="Amount" htmlFor="amount" required>
              <Input id="amount" type="number" min="0.01" step="0.01" required value={form.amount || ''} onChange={(e) => setForm({ ...form, amount: Number(e.target.value) })} />
            </FormField>
            <FormField label="Frequency" htmlFor="frequency" required>
              <Select
                id="frequency"
                value={form.frequency}
                onValueChange={(value) => setForm({ ...form, frequency: value as FeeStructurePayload['frequency'] })}
                options={FEE_FREQUENCIES.map((f) => ({ value: f, label: FEE_FREQUENCY_LABELS[f] }))}
              />
            </FormField>
          </div>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={form.is_active ?? true} onCheckedChange={(checked) => setForm({ ...form, is_active: checked })} />
            Active
          </label>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? 'Save changes' : 'Create structure'}
          </Button>
        </form>
      </Modal>

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="This cannot be undone. Already-generated invoices are unaffected."
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />

      {generateTarget && (
        <GenerateInvoicesModal
          structure={generateTarget}
          sections={sections?.data ?? []}
          open={!!generateTarget}
          onOpenChange={(open) => !open && setGenerateTarget(null)}
        />
      )}
    </div>
  )
}

function GenerateInvoicesModal({
  structure,
  sections,
  open,
  onOpenChange,
}: {
  structure: FeeStructure
  sections: { id: number; name: string; grade_level_id: number; grade_level?: { id: number; name: string } }[]
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const today = new Date().toISOString().slice(0, 10)
  const [form, setForm] = useState<GenerateInvoicesPayload>({ section_id: null, issue_date: today, due_date: today })
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    setIsSubmitting(true)
    try {
      const result = await feeStructuresApi.generateInvoices(structure.id, form)
      toast.success(`Generated ${result.created_count} invoice(s); skipped ${result.skipped_count} already-invoiced student(s).`)
      onOpenChange(false)
    } catch (error) {
      toast.error((error as ApiError).message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={`Generate Invoices — ${structure.name}`}>
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        <p className="text-sm text-muted-foreground">
          Creates one invoice per active student {structure.grade_level ? `in ${structure.grade_level.name}` : 'school-wide'}, applying any student-specific
          discount. Students already invoiced for this structure are skipped automatically.
        </p>
        <FormField label="Section" htmlFor="section_id" hint={structure.grade_level ? 'Optional — narrows to one section of this grade' : 'Optional — leave unset to bill the whole grade/school'}>
          <Select
            id="section_id"
            value={form.section_id ? String(form.section_id) : 'all'}
            onValueChange={(value) => setForm({ ...form, section_id: value === 'all' ? null : Number(value) })}
            options={[
              { value: 'all', label: structure.grade_level ? `All of ${structure.grade_level.name}` : 'All sections' },
              // Different grades commonly reuse the same section names (every
              // grade has an "A"), so once a structure is grade-scoped only
              // that grade's sections are offered — showing every section
              // school-wide under a bare "A"/"B" label would be ambiguous.
              // For a grade-unscoped structure, the grade name is prefixed
              // instead, for the same reason.
              ...sections
                .filter((s) => !structure.grade_level || s.grade_level_id === structure.grade_level.id)
                .map((s) => ({ value: String(s.id), label: structure.grade_level ? s.name : `${s.grade_level?.name ?? '—'} ${s.name}` })),
            ]}
          />
        </FormField>
        <div className="grid grid-cols-2 gap-4">
          <FormField label="Issue Date" htmlFor="issue_date" required>
            <Input id="issue_date" type="date" required value={form.issue_date} onChange={(e) => setForm({ ...form, issue_date: e.target.value })} />
          </FormField>
          <FormField label="Due Date" htmlFor="due_date" required>
            <Input id="due_date" type="date" required value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} />
          </FormField>
        </div>
        <Button type="submit" isLoading={isSubmitting} className="mt-2">
          Generate invoices
        </Button>
      </form>
    </Modal>
  )
}
