import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { LogIn, LogOut, Pencil, Plus } from 'lucide-react'
import { staffAttendanceApi } from '@/api/endpoints/attendance'
import { usersApi } from '@/api/endpoints/users'
import { queryKeys } from '@/api/queryKeys'
import { useAuth } from '@/context/AuthContext'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Card, CardContent, DataTable, DatePicker, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import { AttendanceStatusPicker } from '../components/AttendanceStatusPicker'
import { ATTENDANCE_STATUS_BADGE_VARIANT } from '../statusStyles'
import { formatDate } from '@/utils/formatDate'
import type { StaffAttendanceRecord } from '@/types/attendance'
import type { AttendanceStatus } from '@/types/enums'
import type { ApiError } from '@/api/client'

export function StaffAttendancePage() {
  const { user } = useAuth()
  const { can } = usePermission()
  const queryClient = useQueryClient()
  const { sort, setPage, setSort, queryParams } = usePagination('-date')

  const { data, isLoading } = useQuery({
    queryKey: queryKeys.staffAttendance(queryParams),
    queryFn: () => staffAttendanceApi.list(queryParams),
  })

  const today = new Date().toISOString().slice(0, 10)

  // Deliberately a separate, unpaginated query rather than scanning `data` —
  // the table above can be sorted/paged away from today, but the check-in
  // card must always reflect today's actual state regardless of table state.
  const { data: todaysOwnRecords } = useQuery({
    queryKey: queryKeys.staffAttendance({ 'filter[user_id]': user?.id, 'filter[date]': today, per_page: 1 }),
    queryFn: () => staffAttendanceApi.list({ 'filter[user_id]': user!.id, 'filter[date]': today, per_page: 1 }),
    enabled: !!user,
  })
  const todaysRecord = todaysOwnRecords?.data[0]

  const checkInMutation = useMutation({
    mutationFn: staffAttendanceApi.checkIn,
    onSuccess: () => {
      toast.success('Checked in.')
      queryClient.invalidateQueries({ queryKey: queryKeys.staffAttendance() })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })
  const checkOutMutation = useMutation({
    mutationFn: staffAttendanceApi.checkOut,
    onSuccess: () => {
      toast.success('Checked out.')
      queryClient.invalidateQueries({ queryKey: queryKeys.staffAttendance() })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const [markModalOpen, setMarkModalOpen] = useState(false)
  const [correcting, setCorrecting] = useState<StaffAttendanceRecord | null>(null)

  const columns: DataTableColumn<StaffAttendanceRecord>[] = [
    { key: 'date', header: 'Date', render: (row) => formatDate(row.date) },
    { key: 'user', header: 'Staff', render: (row) => row.user?.full_name ?? `#${row.user_id}` },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={ATTENDANCE_STATUS_BADGE_VARIANT[row.status]}>{row.status_label}</Badge> },
    { key: 'check_in_time', header: 'Check In', render: (row) => row.check_in_time ?? '—' },
    { key: 'check_out_time', header: 'Check Out', render: (row) => row.check_out_time ?? '—' },
    { key: 'remarks', header: 'Remarks', render: (row) => row.remarks ?? '—' },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) =>
        can('staff-attendance.edit') ? (
          <Button variant="outline" size="sm" onClick={() => setCorrecting(row)} aria-label={`Correct attendance for ${row.user?.full_name ?? row.user_id}`}>
            <Pencil className="h-3.5 w-3.5" />
          </Button>
        ) : null,
    },
  ]

  return (
    <div>
      <PageHeader
        title="Staff Attendance"
        description="Self check-in/check-out and staff attendance records."
        actions={
          can('staff-attendance.mark') && (
            <Button onClick={() => setMarkModalOpen(true)}>
              <Plus className="h-4 w-4" /> Mark Attendance
            </Button>
          )
        }
      />

      <Card className="mb-6">
        <CardContent className="flex flex-col items-start justify-between gap-4 pt-4 sm:flex-row sm:items-center sm:pt-6">
          <div>
            <p className="font-medium">Today, {formatDate(today)}</p>
            <p className="text-sm text-muted-foreground">
              {todaysRecord?.check_in_time
                ? `Checked in at ${todaysRecord.check_in_time}${todaysRecord.check_out_time ? `, out at ${todaysRecord.check_out_time}` : ''}`
                : 'You have not checked in today.'}
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => checkInMutation.mutate()} isLoading={checkInMutation.isPending} disabled={!!todaysRecord?.check_in_time}>
              <LogIn className="h-4 w-4" /> Check In
            </Button>
            <Button
              variant="outline"
              onClick={() => checkOutMutation.mutate()}
              isLoading={checkOutMutation.isPending}
              disabled={!todaysRecord?.check_in_time || !!todaysRecord?.check_out_time}
            >
              <LogOut className="h-4 w-4" /> Check Out
            </Button>
          </div>
        </CardContent>
      </Card>

      <DataTable
        columns={columns}
        data={data?.data}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        meta={data?.meta}
        onPageChange={setPage}
        sort={sort}
        onSortChange={setSort}
        emptyTitle="No attendance records yet"
      />

      <MarkStaffAttendanceModal open={markModalOpen} onOpenChange={setMarkModalOpen} />
      <CorrectStaffAttendanceModal record={correcting} onOpenChange={(open) => !open && setCorrecting(null)} />
    </div>
  )
}

function MarkStaffAttendanceModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const { data: users } = useQuery({ queryKey: queryKeys.users({ per_page: 200 }), queryFn: () => usersApi.list({ per_page: 200 }), enabled: open })
  const [userId, setUserId] = useState<number | null>(null)
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [status, setStatus] = useState<AttendanceStatus>('present')
  const [remarks, setRemarks] = useState('')

  const markMutation = useMutation({
    mutationFn: () => staffAttendanceApi.mark({ date, entries: [{ user_id: userId!, status, remarks: remarks || null }] }),
    onSuccess: () => {
      toast.success('Attendance saved.')
      queryClient.invalidateQueries({ queryKey: queryKeys.staffAttendance() })
      onOpenChange(false)
      setUserId(null)
      setRemarks('')
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title="Mark Staff Attendance">
      <form
        onSubmit={(e) => {
          e.preventDefault()
          markMutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label="Staff member" htmlFor="user_id" required>
          <Select
            id="user_id"
            value={userId ? String(userId) : undefined}
            onValueChange={(value) => setUserId(Number(value))}
            options={(users?.data ?? []).map((u) => ({ value: String(u.id), label: u.full_name }))}
            placeholder="Select a staff member…"
          />
        </FormField>
        <FormField label="Date" htmlFor="date" required>
          <DatePicker id="date" required value={date} onChange={(e) => setDate(e.target.value)} />
        </FormField>
        <FormField label="Status" htmlFor="status" required>
          <AttendanceStatusPicker value={status} onChange={setStatus} />
        </FormField>
        <FormField label="Remarks" htmlFor="remarks">
          <Input id="remarks" value={remarks} onChange={(e) => setRemarks(e.target.value)} placeholder="Optional" />
        </FormField>
        <Button type="submit" isLoading={markMutation.isPending} disabled={!userId} className="mt-2">
          Save
        </Button>
      </form>
    </Modal>
  )
}

function CorrectStaffAttendanceModal({ record, onOpenChange }: { record: StaffAttendanceRecord | null; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const [status, setStatus] = useState<AttendanceStatus>(record?.status ?? 'present')
  const [remarks, setRemarks] = useState(record?.remarks ?? '')

  const correctMutation = useMutation({
    mutationFn: () => staffAttendanceApi.correct(record!.id, { status, remarks: remarks || null }),
    onSuccess: () => {
      toast.success('Attendance corrected.')
      queryClient.invalidateQueries({ queryKey: queryKeys.staffAttendance() })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  if (!record) return null

  return (
    <Modal open={!!record} onOpenChange={onOpenChange} title={`Correct attendance — ${record.user?.full_name ?? ''}`} description={formatDate(record.date)}>
      <form
        onSubmit={(e) => {
          e.preventDefault()
          correctMutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label="Status" htmlFor="correct-status" required>
          <AttendanceStatusPicker value={status} onChange={setStatus} />
        </FormField>
        <FormField label="Remarks" htmlFor="correct-remarks">
          <Input id="correct-remarks" value={remarks} onChange={(e) => setRemarks(e.target.value)} placeholder="Optional" />
        </FormField>
        <Button type="submit" isLoading={correctMutation.isPending} className="mt-2">
          Save correction
        </Button>
      </form>
    </Modal>
  )
}
