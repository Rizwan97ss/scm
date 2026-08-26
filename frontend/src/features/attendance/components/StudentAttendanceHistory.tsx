import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { studentAttendanceApi } from '@/api/endpoints/attendance'
import { queryKeys } from '@/api/queryKeys'
import { Badge, DatePicker, Skeleton, StatCard, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { ATTENDANCE_STATUS_BADGE_VARIANT } from '../statusStyles'
import { formatDate } from '@/utils/formatDate'

function startOfMonth(): string {
  const d = new Date()
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10)
}

/** Shared by the staff-facing Student Profile page and the Parent Portal child profile — same summary + record list, different data-fetch permission context enforced entirely server-side. */
export function StudentAttendanceHistory({ studentId }: { studentId: number }) {
  const { t } = useTranslation()
  const [from, setFrom] = useState(startOfMonth)
  const [to, setTo] = useState(() => new Date().toISOString().slice(0, 10))

  const { data: summary, isLoading: summaryLoading } = useQuery({
    queryKey: queryKeys.studentAttendanceSummary(studentId, { from, to }),
    queryFn: () => studentAttendanceApi.summary({ student_id: studentId, from, to }),
  })

  const { data: records, isLoading: recordsLoading } = useQuery({
    queryKey: queryKeys.studentAttendance({ studentId, from, to }),
    queryFn: () => studentAttendanceApi.list({ 'filter[student_id]': studentId, per_page: 100, sort: '-date' }),
  })

  const filteredRecords = (records?.data ?? []).filter((r) => r.date >= from && r.date <= to)

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end gap-3">
        <div>
          <label className="mb-1 block text-xs text-muted-foreground">{t('common.from')}</label>
          <DatePicker value={from} onChange={(e) => setFrom(e.target.value)} className="h-9 w-40" />
        </div>
        <div>
          <label className="mb-1 block text-xs text-muted-foreground">{t('common.to')}</label>
          <DatePicker value={to} onChange={(e) => setTo(e.target.value)} className="h-9 w-40" />
        </div>
      </div>

      {summaryLoading ? (
        <Skeleton className="h-24 w-full" />
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <StatCard label={t('attendance.attendancePercentLabel')} value={summary?.percentage !== null && summary?.percentage !== undefined ? `${summary.percentage}%` : '—'} />
          <StatCard label={t('attendance.daysMarkedLabel')} value={String(summary?.total_marked ?? 0)} />
          <StatCard label={t('attendance.absencesLabel')} value={String(summary?.counts.absent ?? 0)} />
        </div>
      )}

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('common.date')}</TableHead>
            <TableHead>{t('common.status')}</TableHead>
            <TableHead>{t('common.remarks')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {recordsLoading &&
            Array.from({ length: 3 }).map((_, i) => (
              <TableRow key={i}>
                <TableCell colSpan={3}>
                  <Skeleton className="h-4 w-full" />
                </TableCell>
              </TableRow>
            ))}
          {!recordsLoading && filteredRecords.length === 0 && (
            <TableRow>
              <TableCell colSpan={3} className="text-center text-sm text-muted-foreground">
                {t('attendance.noRecordsInRange')}
              </TableCell>
            </TableRow>
          )}
          {!recordsLoading &&
            filteredRecords.map((record) => (
              <TableRow key={record.id}>
                <TableCell>{formatDate(record.date)}</TableCell>
                <TableCell>
                  <Badge variant={ATTENDANCE_STATUS_BADGE_VARIANT[record.status]}>{record.status_label}</Badge>
                </TableCell>
                <TableCell className="text-muted-foreground">{record.remarks ?? '—'}</TableCell>
              </TableRow>
            ))}
        </TableBody>
      </Table>
    </div>
  )
}
