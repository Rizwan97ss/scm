import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Send, Trash2 } from 'lucide-react'
import { certificateTemplatesApi } from '@/api/endpoints/certificates'
import { studentsApi } from '@/api/endpoints/students'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, ConfirmDialog, DataTable, FormField, Input, Modal, Select, Textarea, type DataTableColumn } from '@/components/ui'
import type { CertificateTemplate, CertificateTemplatePayload } from '@/types/certificates'
import type { ApiError } from '@/api/client'

const EMPTY_FORM: CertificateTemplatePayload = { name: '', type: '', body: '', is_active: true }

export function CertificateTemplatesPage() {
  const { can } = usePermission()
  const canManage = can('certificates.edit') || can('certificates.create')
  const { sort, setPage, setSort, queryParams } = usePagination('name', 'name')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(certificateTemplatesApi, queryKeys.certificateTemplates, queryParams, 'Certificate template')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<CertificateTemplate | null>(null)
  const [form, setForm] = useState<CertificateTemplatePayload>(EMPTY_FORM)
  const [deleting, setDeleting] = useState<CertificateTemplate | null>(null)
  const [issuing, setIssuing] = useState<CertificateTemplate | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(template: CertificateTemplate) {
    setEditing(template)
    setForm({ name: template.name, type: template.type, body: template.body, is_active: template.is_active })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<CertificateTemplate>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'type', header: 'Type', render: (row) => row.type },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? 'Active' : 'Inactive'}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {can('certificates.issue') && (
            <Button variant="outline" size="sm" onClick={() => setIssuing(row)}>
              <Send className="h-3.5 w-3.5" /> Issue
            </Button>
          )}
          {canManage && (
            <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
              Edit
            </Button>
          )}
          {can('certificates.delete') && (
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
        title="Certificate Templates"
        description="Reusable certificate wording — issue one to a student to snapshot it as a real certificate."
        actions={
          can('certificates.create') && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> New Template
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
        sort={sort}
        onSortChange={setSort}
        emptyTitle="No certificate templates yet"
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? 'Edit Template' : 'New Template'}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <div className="grid grid-cols-2 gap-4">
            <FormField label="Name" htmlFor="name" required>
              <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </FormField>
            <FormField label="Type" htmlFor="type" required hint="e.g. Bonafide, Transfer">
              <Input id="type" required value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} />
            </FormField>
          </div>
          <FormField
            label="Body"
            htmlFor="body"
            required
            hint="Placeholders: {{student_name}}, {{admission_number}}, {{grade_level}}, {{section}}, {{school_name}}, {{date}}"
          >
            <Textarea id="body" rows={6} required value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? 'Save changes' : 'Create template'}
          </Button>
        </form>
      </Modal>

      {issuing && <IssueCertificateModal template={issuing} open={!!issuing} onOpenChange={(open) => !open && setIssuing(null)} />}

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="This cannot be undone."
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />
    </div>
  )
}

function IssueCertificateModal({ template, open, onOpenChange }: { template: CertificateTemplate; open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const { data: students } = useQuery({ queryKey: queryKeys.students({ per_page: 100 }), queryFn: () => studentsApi.list({ per_page: 100 }) })
  const [studentId, setStudentId] = useState<number | undefined>()

  const mutation = useMutation({
    mutationFn: () => certificateTemplatesApi.issue(template.id, { student_id: studentId! }),
    onSuccess: () => {
      toast.success('Certificate issued.')
      queryClient.invalidateQueries({ queryKey: queryKeys.certificates().slice(0, 1) })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={`Issue “${template.name}”`}>
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label="Student" htmlFor="student_id" required>
          <Select
            id="student_id"
            value={studentId ? String(studentId) : undefined}
            onValueChange={(value) => setStudentId(Number(value))}
            options={(students?.data ?? []).map((s) => ({ value: String(s.id), label: `${s.full_name} (${s.admission_number})` }))}
            placeholder="Select a student"
          />
        </FormField>
        <Button type="submit" isLoading={mutation.isPending} disabled={!studentId} className="mt-2">
          Issue certificate
        </Button>
      </form>
    </Modal>
  )
}
