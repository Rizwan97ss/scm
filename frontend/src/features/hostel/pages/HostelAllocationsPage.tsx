import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { LogOut, Plus } from 'lucide-react'
import { hostelAllocationsApi, hostelRoomsApi } from '@/api/endpoints/hostel'
import { studentsApi } from '@/api/endpoints/students'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import { formatDate } from '@/utils/formatDate'
import type { HostelAllocation, HostelAllocationPayload } from '@/types/hostel'
import type { ApiError } from '@/api/client'

export function HostelAllocationsPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const canManage = can('hostel.manage')
  const { setPage, queryParams } = usePagination('-allocated_date')
  const listQuery = useQuery({ queryKey: queryKeys.hostelAllocations(queryParams), queryFn: () => hostelAllocationsApi.list(queryParams) })
  const queryClient = useQueryClient()

  const [allocateModalOpen, setAllocateModalOpen] = useState(false)

  const vacateMutation = useMutation({
    mutationFn: (id: number) => hostelAllocationsApi.vacate(id),
    onSuccess: () => {
      toast.success(t('hostel.allocationVacatedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.hostelAllocations().slice(0, 1) })
      queryClient.invalidateQueries({ queryKey: queryKeys.hostelRooms().slice(0, 1) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const columns: DataTableColumn<HostelAllocation>[] = [
    { key: 'student', header: t('entities.student'), render: (row) => row.student?.full_name ?? '—' },
    { key: 'hostel', header: t('entities.hostel'), render: (row) => row.hostel_room?.hostel?.name ?? '—' },
    { key: 'room', header: t('hostel.roomNumberColumn'), render: (row) => row.hostel_room?.room_number ?? '—' },
    { key: 'bed_number', header: t('hostel.bedColumn'), render: (row) => row.bed_number ?? '—' },
    { key: 'allocated_date', header: t('hostel.allocatedColumn'), render: (row) => formatDate(row.allocated_date) },
    { key: 'status', header: t('common.status'), render: (row) => <Badge variant={row.status === 'allocated' ? 'success' : 'default'}>{row.status_label}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) =>
        canManage && row.status === 'allocated' ? (
          <Button
            variant="outline"
            size="sm"
            isLoading={vacateMutation.isPending && vacateMutation.variables === row.id}
            onClick={() => vacateMutation.mutate(row.id)}
          >
            <LogOut className="h-3.5 w-3.5" /> {t('hostel.vacateAction')}
          </Button>
        ) : null,
    },
  ]

  return (
    <div>
      <PageHeader
        title={t('hostel.allocationsTitle')}
        description={t('hostel.allocationsDescription')}
        actions={
          canManage && (
            <Button onClick={() => setAllocateModalOpen(true)}>
              <Plus className="h-4 w-4" /> {t('hostel.allocateStudent')}
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
        emptyTitle={t('common.noItemsYet', { items: t('hostel.allocationsTitle') })}
      />

      {allocateModalOpen && <AllocateModal open={allocateModalOpen} onOpenChange={setAllocateModalOpen} />}
    </div>
  )
}

function AllocateModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { data: students } = useQuery({ queryKey: queryKeys.students({ per_page: 100 }), queryFn: () => studentsApi.list({ per_page: 100 }) })
  const { data: rooms } = useQuery({ queryKey: queryKeys.hostelRooms({ per_page: 100 }), queryFn: () => hostelRoomsApi.list({ per_page: 100 }) })

  const [form, setForm] = useState<HostelAllocationPayload>({
    student_id: 0,
    hostel_room_id: 0,
    bed_number: '',
    allocated_date: new Date().toISOString().slice(0, 10),
  })

  const mutation = useMutation({
    mutationFn: () => hostelAllocationsApi.allocate(form),
    onSuccess: () => {
      toast.success(t('hostel.studentAllocatedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.hostelAllocations().slice(0, 1) })
      queryClient.invalidateQueries({ queryKey: queryKeys.hostelRooms().slice(0, 1) })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={t('hostel.allocateStudentModalTitle')}>
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label={t('entities.student')} htmlFor="student_id" required>
          <Select
            id="student_id"
            value={form.student_id ? String(form.student_id) : undefined}
            onValueChange={(value) => setForm({ ...form, student_id: Number(value) })}
            options={(students?.data ?? []).map((s) => ({ value: String(s.id), label: `${s.full_name} (${s.admission_number})` }))}
            placeholder={t('hostel.selectStudentPlaceholder')}
          />
        </FormField>
        <FormField label={t('hostel.roomNumberColumn')} htmlFor="hostel_room_id" required>
          <Select
            id="hostel_room_id"
            value={form.hostel_room_id ? String(form.hostel_room_id) : undefined}
            onValueChange={(value) => setForm({ ...form, hostel_room_id: Number(value) })}
            options={(rooms?.data ?? []).map((r) => ({
              value: String(r.id),
              label: `${r.hostel?.name ?? ''} — ${r.room_number} (${r.occupied_count}/${r.capacity})`,
              disabled: r.occupied_count >= r.capacity,
            }))}
            placeholder={t('hostel.selectRoomPlaceholder')}
          />
        </FormField>
        <FormField label={t('hostel.bedNumberLabel')} htmlFor="bed_number" hint={t('common.optional')}>
          <Input id="bed_number" value={form.bed_number ?? ''} onChange={(e) => setForm({ ...form, bed_number: e.target.value })} />
        </FormField>
        <FormField label={t('hostel.allocatedDateLabel')} htmlFor="allocated_date" required>
          <Input id="allocated_date" type="date" required value={form.allocated_date} onChange={(e) => setForm({ ...form, allocated_date: e.target.value })} />
        </FormField>
        <Button type="submit" isLoading={mutation.isPending} disabled={!form.student_id || !form.hostel_room_id} className="mt-2">
          {t('hostel.allocateAction')}
        </Button>
      </form>
    </Modal>
  )
}
