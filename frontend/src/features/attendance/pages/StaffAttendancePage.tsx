import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
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
  const { t } = useTranslation()
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
      toast.success(t('attendance.checkedInToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.staffAttendance() })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })
  const checkOutMutation = useMutation({
    mutationFn: staffAttendanceApi.checkOut,
    onSuccess: () => {
      toast.success(t('attendance.checkedOutToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.staffAttendance() })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const [markModalOpen, setMarkModalOpen] = useState(false)
  const [correcting, setCorrecting] = useState<StaffAttendanceRecord | null>(null)

  const columns: DataTableColumn<StaffAttendanceRecord>[] = [
    { key: 'date', header: t('common.date'), render: (row) => formatDate(row.date) },
    { key: 'user', header: t('attendance.staffColumn'), render: (row) => row.user?.full_name ?? `#${row.user_id}` },
    { key: 'status', header: t('common.status'), render: (row) => <Badge variant={ATTENDANCE_STATUS_BADGE_VARIANT[row.status]}>{row.status_label}</Badge> },
    { key: 'check_in_time', header: t('attendance.checkInColumn'), render: (row) => row.check_in_time ?? '—' },
    { key: 'check_out_time', header: t('attendance.checkOutColumn'), render: (row) => row.check_out_time ?? '—' },
    { key: 'remarks', header: t('common.remarks'), render: (row) => row.remarks ?? '—' },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) =>
        can('staff-attendance.edit') ? (
          <Button variant="outline" size="sm" onClick={() => setCorrecting(row)} aria-label={t('attendance.correctAttendanceAria', { name: row.user?.full_name ?? row.user_id })}>
            <Pencil className="h-3.5 w-3.5" />
          </Button>
        ) : null,
    },
  ]

  return (
    <div>
      <PageHeader
        title={t('nav.staff_attendance')}
        description={t('attendance.staffAttendanceDescription')}
        actions={
          can('staff-attendance.mark') && (
            <Button onClick={() => setMarkModalOpen(true)}>
              <Plus className="h-4 w-4" /> {t('attendance.markAttendance')}
            </Button>
          )
        }
      />

      <Card className="mb-6">
        <CardContent className="flex flex-col items-start justify-between gap-4 pt-4 sm:flex-row sm:items-center sm:pt-6">
          <div>
            <p className="font-medium">{t('attendance.todayLabel', { date: formatDate(today) })}</p>
            <p className="text-sm text-muted-foreground">
              {todaysRecord?.check_in_time
                ? todaysRecord.check_out_time
                  ? t('attendance.checkedInOutStatus', { checkIn: todaysRecord.check_in_time, checkOut: todaysRecord.check_out_time })
                  : t('attendance.checkedInStatus', { checkIn: todaysRecord.check_in_time })
                : t('attendance.notCheckedInToday')}
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => checkInMutation.mutate()} isLoading={checkInMutation.isPending} disabled={!!todaysRecord?.check_in_time}>
              <LogIn className="h-4 w-4" /> {t('attendance.checkIn')}
            </Button>
            <Button
              variant="outline"
              onClick={() => checkOutMutation.mutate()}
              isLoading={checkOutMutation.isPending}
              disabled={!todaysRecord?.check_in_time || !!todaysRecord?.check_out_time}
            >
              <LogOut className="h-4 w-4" /> {t('attendance.checkOut')}
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
        emptyTitle={t('attendance.noAttendanceRecordsYet')}
      />

      <MarkStaffAttendanceModal open={markModalOpen} onOpenChange={setMarkModalOpen} />
      <CorrectStaffAttendanceModal record={correcting} onOpenChange={(open) => !open && setCorrecting(null)} />
    </div>
  )
}

function MarkStaffAttendanceModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { data: users } = useQuery({ queryKey: queryKeys.users({ per_page: 200 }), queryFn: () => usersApi.list({ per_page: 200 }), enabled: open })
  const [userId, setUserId] = useState<number | null>(null)
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [status, setStatus] = useState<AttendanceStatus>('present')
  const [remarks, setRemarks] = useState('')

  const markMutation = useMutation({
    mutationFn: () => staffAttendanceApi.mark({ date, entries: [{ user_id: userId!, status, remarks: remarks || null }] }),
    onSuccess: () => {
      toast.success(t('attendance.attendanceSavedSingleToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.staffAttendance() })
      onOpenChange(false)
      setUserId(null)
      setRemarks('')
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={t('attendance.markStaffAttendanceTitle')}>
      <form
        onSubmit={(e) => {
          e.preventDefault()
          markMutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label={t('library.staffMember')} htmlFor="user_id" required>
          <Select
            id="user_id"
            value={userId ? String(userId) : undefined}
            onValueChange={(value) => setUserId(Number(value))}
            options={(users?.data ?? []).map((u) => ({ value: String(u.id), label: u.full_name }))}
            placeholder={t('hr.selectStaffMember')}
          />
        </FormField>
        <FormField label={t('common.date')} htmlFor="date" required>
          <DatePicker id="date" required value={date} onChange={(e) => setDate(e.target.value)} />
        </FormField>
        <FormField label={t('common.status')} htmlFor="status" required>
          <AttendanceStatusPicker value={status} onChange={setStatus} />
        </FormField>
        <FormField label={t('common.remarks')} htmlFor="remarks">
          <Input id="remarks" value={remarks} onChange={(e) => setRemarks(e.target.value)} placeholder={t('common.optional')} />
        </FormField>
        <Button type="submit" isLoading={markMutation.isPending} disabled={!userId} className="mt-2">
          {t('common.save')}
        </Button>
      </form>
    </Modal>
  )
}

function CorrectStaffAttendanceModal({ record, onOpenChange }: { record: StaffAttendanceRecord | null; onOpenChange: (open: boolean) => void }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [status, setStatus] = useState<AttendanceStatus>(record?.status ?? 'present')
  const [remarks, setRemarks] = useState(record?.remarks ?? '')

  const correctMutation = useMutation({
    mutationFn: () => staffAttendanceApi.correct(record!.id, { status, remarks: remarks || null }),
    onSuccess: () => {
      toast.success(t('attendance.attendanceCorrectedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.staffAttendance() })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  if (!record) return null

  return (
    <Modal
      open={!!record}
      onOpenChange={onOpenChange}
      title={t('attendance.correctAttendanceTitle', { name: record.user?.full_name ?? '' })}
      description={formatDate(record.date)}
    >
      <form
        onSubmit={(e) => {
          e.preventDefault()
          correctMutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label={t('common.status')} htmlFor="correct-status" required>
          <AttendanceStatusPicker value={status} onChange={setStatus} />
        </FormField>
        <FormField label={t('common.remarks')} htmlFor="correct-remarks">
          <Input id="correct-remarks" value={remarks} onChange={(e) => setRemarks(e.target.value)} placeholder={t('common.optional')} />
        </FormField>
        <Button type="submit" isLoading={correctMutation.isPending} className="mt-2">
          {t('attendance.saveCorrection')}
        </Button>
      </form>
    </Modal>
  )
}
