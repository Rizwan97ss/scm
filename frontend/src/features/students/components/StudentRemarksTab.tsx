import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'
import { studentRemarksApi } from '@/api/endpoints/homework'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { Badge, Button, Checkbox, EmptyState, FormField, Modal, Select, Skeleton, Textarea } from '@/components/ui'
import { formatDateTime } from '@/utils/formatDate'
import { REMARK_CATEGORIES, REMARK_CATEGORY_LABELS } from '@/types/homework'
import type { RemarkCategory, StudentRemarkPayload } from '@/types/homework'
import type { ApiError } from '@/api/client'

const EMPTY_FORM: StudentRemarkPayload = { student_id: 0, category: 'general', body: '', visible_to_guardian: true }

export function StudentRemarksTab({ studentId }: { studentId: number }) {
  const { can } = usePermission()
  const queryClient = useQueryClient()
  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<StudentRemarkPayload>({ ...EMPTY_FORM, student_id: studentId })

  const { data: remarks, isLoading } = useQuery({
    queryKey: queryKeys.studentRemarks({ 'filter[student_id]': studentId }),
    queryFn: () => studentRemarksApi.list({ 'filter[student_id]': studentId, per_page: 50 }),
  })

  const createMutation = useMutation({
    mutationFn: () => studentRemarksApi.create(form),
    onSuccess: () => {
      toast.success('Remark added.')
      queryClient.invalidateQueries({ queryKey: queryKeys.studentRemarks({ 'filter[student_id]': studentId }).slice(0, 1) })
      setModalOpen(false)
      setForm({ ...EMPTY_FORM, student_id: studentId })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <div className="flex flex-col gap-4">
      {can('remarks.create') && (
        <div className="flex justify-end">
          <Button onClick={() => setModalOpen(true)}>
            <Plus className="h-4 w-4" /> Add Remark
          </Button>
        </div>
      )}

      {isLoading && <Skeleton className="h-24 w-full" />}

      {!isLoading && (remarks?.data.length ?? 0) === 0 && <EmptyState title="No remarks yet" />}

      {!isLoading && (remarks?.data.length ?? 0) > 0 && (
        <ul className="flex flex-col gap-3">
          {remarks!.data.map((remark) => (
            <li key={remark.id} className="rounded-lg border border-border p-4">
              <div className="mb-1 flex items-center gap-2">
                <Badge variant="outline">{REMARK_CATEGORY_LABELS[remark.category]}</Badge>
                {!remark.visible_to_guardian && <Badge variant="default">Staff only</Badge>}
                <span className="ml-auto text-xs text-muted-foreground">{formatDateTime(remark.created_at)}</span>
              </div>
              <p className="text-sm">{remark.body}</p>
              {remark.author && <p className="mt-1 text-xs text-muted-foreground">— {remark.author.full_name}</p>}
            </li>
          ))}
        </ul>
      )}

      <Modal open={modalOpen} onOpenChange={setModalOpen} title="Add Remark">
        <form
          onSubmit={(e) => {
            e.preventDefault()
            createMutation.mutate()
          }}
          className="flex flex-col gap-4"
          noValidate
        >
          <FormField label="Category" htmlFor="category">
            <Select
              id="category"
              value={form.category}
              onValueChange={(value) => setForm({ ...form, category: value as RemarkCategory })}
              options={REMARK_CATEGORIES.map((c) => ({ value: c, label: REMARK_CATEGORY_LABELS[c] }))}
            />
          </FormField>
          <FormField label="Remark" htmlFor="body" required>
            <Textarea id="body" rows={4} required value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} />
          </FormField>
          <div className="flex items-center gap-2">
            <Checkbox checked={form.visible_to_guardian} onCheckedChange={(checked) => setForm({ ...form, visible_to_guardian: checked })} aria-label="Visible to guardian" />
            <label className="text-sm">Visible to parent/guardian</label>
          </div>
          <Button type="submit" isLoading={createMutation.isPending} className="mt-2">
            Add remark
          </Button>
        </form>
      </Modal>
    </div>
  )
}
