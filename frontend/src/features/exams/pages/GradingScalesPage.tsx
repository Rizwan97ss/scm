import { useState } from 'react'
import { useFieldArray, useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'
import { gradingScalesApi } from '@/api/endpoints/exams'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Checkbox, ConfirmDialog, DataTable, FormField, Input, Modal, type DataTableColumn } from '@/components/ui'
import type { GradeBandInput, GradingScale, GradingScalePayload } from '@/types/exam'

const EMPTY_BAND: GradeBandInput = { min_percentage: 0, max_percentage: 100, grade_label: '', grade_point: null, remark: '' }

export function GradingScalesPage() {
  const { can } = usePermission()
  const { setPage, queryParams } = usePagination('name')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(gradingScalesApi, queryKeys.gradingScales, queryParams, 'Grading scale')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<GradingScale | null>(null)
  const [deleting, setDeleting] = useState<GradingScale | null>(null)

  const { register, control, handleSubmit, reset, watch, setValue, formState: { errors } } = useForm<GradingScalePayload>({
    defaultValues: { name: '', is_default: false, grade_bands: [EMPTY_BAND] },
  })
  const { fields, append, remove } = useFieldArray({ control, name: 'grade_bands' })

  function openCreate() {
    setEditing(null)
    reset({ name: '', is_default: false, grade_bands: [EMPTY_BAND] })
    setModalOpen(true)
  }

  function openEdit(scale: GradingScale) {
    setEditing(scale)
    reset({
      name: scale.name,
      is_default: scale.is_default,
      grade_bands: scale.grade_bands.map((b) => ({ min_percentage: b.min_percentage, max_percentage: b.max_percentage, grade_label: b.grade_label, grade_point: b.grade_point, remark: b.remark })),
    })
    setModalOpen(true)
  }

  async function onSubmit(values: GradingScalePayload) {
    try {
      if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: values })
      else await createMutation.mutateAsync(values)
      setModalOpen(false)
    } catch {
      toast.error('Check the grade bands for validation errors (e.g. min/max ranges).')
    }
  }

  const columns: DataTableColumn<GradingScale>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'default', header: 'Default', render: (row) => (row.is_default ? <Badge variant="primary">Default</Badge> : '—') },
    { key: 'bands', header: 'Bands', render: (row) => row.grade_bands.length },
    {
      key: 'actions', header: '', align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {can('grading.manage') && <Button variant="outline" size="sm" onClick={() => openEdit(row)}>Edit</Button>}
          {can('grading.manage') && (
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
        title="Grading Scales"
        description="Define how raw marks map to letter grades, GPA points, and remarks."
        actions={can('grading.manage') && <Button onClick={openCreate}><Plus className="h-4 w-4" /> New Scale</Button>}
      />
      <DataTable columns={columns} data={listQuery.data?.data} rowKey={(r) => r.id} isLoading={listQuery.isLoading} meta={listQuery.data?.meta} onPageChange={setPage} emptyTitle="No grading scales yet" />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Grading Scale' : 'New Grading Scale'} size="lg">
        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4" noValidate>
          <FormField label="Scale name" htmlFor="name" required error={errors.name?.message}>
            <Input id="name" required {...register('name', { required: 'Required' })} />
          </FormField>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={watch('is_default')} onCheckedChange={(checked) => setValue('is_default', checked)} />
            Set as the default scale for this school
          </label>

          <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold">Grade Bands</h3>
              <Button type="button" variant="outline" size="sm" onClick={() => append(EMPTY_BAND)}>
                <Plus className="h-3.5 w-3.5" /> Add band
              </Button>
            </div>
            {fields.map((field, index) => (
              <div key={field.id} className="grid grid-cols-12 items-end gap-2 rounded-md border border-border p-2">
                <div className="col-span-2">
                  <label className="text-xs text-muted-foreground">Min %</label>
                  <Input type="number" step="0.01" {...register(`grade_bands.${index}.min_percentage`, { valueAsNumber: true, required: true })} />
                </div>
                <div className="col-span-2">
                  <label className="text-xs text-muted-foreground">Max %</label>
                  <Input type="number" step="0.01" {...register(`grade_bands.${index}.max_percentage`, { valueAsNumber: true, required: true })} />
                </div>
                <div className="col-span-2">
                  <label className="text-xs text-muted-foreground">Label</label>
                  <Input placeholder="A+" {...register(`grade_bands.${index}.grade_label`, { required: true })} />
                </div>
                <div className="col-span-2">
                  <label className="text-xs text-muted-foreground">GPA</label>
                  <Input type="number" step="0.01" {...register(`grade_bands.${index}.grade_point`, { valueAsNumber: true })} />
                </div>
                <div className="col-span-3">
                  <label className="text-xs text-muted-foreground">Remark</label>
                  <Input placeholder="Excellent" {...register(`grade_bands.${index}.remark`)} />
                </div>
                <div className="col-span-1">
                  <Button type="button" variant="outline" size="icon" onClick={() => remove(index)} disabled={fields.length <= 1} aria-label="Remove band">
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
            ))}
          </div>

          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? 'Save changes' : 'Create scale'}
          </Button>
        </form>
      </Modal>

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={`Delete "${deleting?.name}"?`}
        description="Exam subjects using this scale will fall back to the school default."
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />
    </div>
  )
}
