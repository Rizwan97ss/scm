import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { LogOut, Plus } from 'lucide-react'
import { visitorsApi } from '@/api/endpoints/frontDesk'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, Textarea, type DataTableColumn } from '@/components/ui'
import { formatDateTime } from '@/utils/formatDate'
import type { Visitor, VisitorPayload } from '@/types/frontDesk'
import type { ApiError } from '@/api/client'

const EMPTY_FORM: VisitorPayload = { name: '', phone: '', purpose: '', whom_to_meet: '', notes: '' }

export function VisitorsPage() {
  const { can } = usePermission()
  const canManage = can('front-desk.manage')
  const { setPage, queryParams } = usePagination('-check_in_time')
  const listQuery = useQuery({ queryKey: queryKeys.visitors(queryParams), queryFn: () => visitorsApi.list(queryParams) })
  const queryClient = useQueryClient()

  const [checkInModalOpen, setCheckInModalOpen] = useState(false)

  const checkOutMutation = useMutation({
    mutationFn: (id: number) => visitorsApi.checkOut(id),
    onSuccess: () => {
      toast.success('Visitor checked out.')
      queryClient.invalidateQueries({ queryKey: queryKeys.visitors().slice(0, 1) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const columns: DataTableColumn<Visitor>[] = [
    { key: 'name', header: 'Name', render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'purpose', header: 'Purpose', render: (row) => row.purpose },
    { key: 'whom_to_meet', header: 'To Meet', render: (row) => row.whom_to_meet ?? '—' },
    { key: 'check_in', header: 'Checked In', render: (row) => formatDateTime(row.check_in_time) },
    { key: 'check_out', header: 'Checked Out', render: (row) => (row.check_out_time ? formatDateTime(row.check_out_time) : '—') },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={row.check_out_time ? 'default' : 'success'}>{row.check_out_time ? 'Checked Out' : 'Checked In'}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) =>
        canManage && !row.check_out_time ? (
          <Button variant="outline" size="sm" isLoading={checkOutMutation.isPending} onClick={() => checkOutMutation.mutate(row.id)}>
            <LogOut className="h-3.5 w-3.5" /> Check Out
          </Button>
        ) : null,
    },
  ]

  return (
    <div>
      <PageHeader
        title="Visitors"
        description="Front-desk visitor log."
        actions={
          canManage && (
            <Button onClick={() => setCheckInModalOpen(true)}>
              <Plus className="h-4 w-4" /> Log Visitor
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
        emptyTitle="No visitors logged yet"
      />

      {checkInModalOpen && <CheckInModal open={checkInModalOpen} onOpenChange={setCheckInModalOpen} />}
    </div>
  )
}

function CheckInModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<VisitorPayload>(EMPTY_FORM)

  const mutation = useMutation({
    mutationFn: () => visitorsApi.checkIn(form),
    onSuccess: () => {
      toast.success('Visitor checked in.')
      queryClient.invalidateQueries({ queryKey: queryKeys.visitors().slice(0, 1) })
      setForm(EMPTY_FORM)
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title="Log Visitor">
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label="Name" htmlFor="name" required>
          <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        </FormField>
        <div className="grid grid-cols-2 gap-4">
          <FormField label="Phone" htmlFor="phone">
            <Input id="phone" value={form.phone ?? ''} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          </FormField>
          <FormField label="Whom to Meet" htmlFor="whom_to_meet">
            <Input id="whom_to_meet" value={form.whom_to_meet ?? ''} onChange={(e) => setForm({ ...form, whom_to_meet: e.target.value })} />
          </FormField>
        </div>
        <FormField label="Purpose" htmlFor="purpose" required>
          <Input id="purpose" required value={form.purpose} onChange={(e) => setForm({ ...form, purpose: e.target.value })} />
        </FormField>
        <FormField label="Notes" htmlFor="notes" hint="Optional">
          <Textarea id="notes" rows={2} value={form.notes ?? ''} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
        </FormField>
        <Button type="submit" isLoading={mutation.isPending} className="mt-2">
          Check in
        </Button>
      </form>
    </Modal>
  )
}
