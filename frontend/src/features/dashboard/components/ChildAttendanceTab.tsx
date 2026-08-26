import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { parentPortalApi } from '@/api/endpoints/dashboard'
import { queryKeys } from '@/api/queryKeys'
import { Badge, DatePicker, Skeleton, StatCard, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { ATTENDANCE_STATUS_BADGE_VARIANT } from '@/features/attendance/statusStyles'
import { formatDate } from '@/utils/formatDate'

function startOfMonth(): string {
  const d = new Date()
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10)
}

/**
 * Uses the dedicated /parent/children/{id}/attendance endpoint rather than the
 * staff-facing /attendance/students/* ones StudentAttendanceHistory hits —
 * parent portal routes are never gated behind the staff permission matrix
 * (same as /parent/children itself), so this keeps working even if a School
 * Admin edits the Parent role's permissions.
 */
export function ChildAttendanceTab({ studentId }: { studentId: number }) {
  const { t } = useTranslation()
  const [from, setFrom] = useState(startOfMonth)
  const [to, setTo] = useState(() => new Date().toISOString().slice(0, 10))

  const { data, isLoading } = useQuery({
    queryKey: queryKeys.parentChildAttendance(studentId, { from, to }),
    queryFn: () => parentPortalApi.childAttendance(studentId, { from, to }),
  })

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

      {isLoading ? (
        <Skeleton className="h-24 w-full" />
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <StatCard label={t('attendance.attendancePercentLabel')} value={data?.summary.percentage !== null && data?.summary.percentage !== undefined ? `${data.summary.percentage}%` : '—'} />
          <StatCard label={t('attendance.daysMarkedLabel')} value={String(data?.summary.total_marked ?? 0)} />
          <StatCard label={t('attendance.absencesLabel')} value={String(data?.summary.counts.absent ?? 0)} />
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
          {isLoading &&
            Array.from({ length: 3 }).map((_, i) => (
              <TableRow key={i}>
                <TableCell colSpan={3}>
                  <Skeleton className="h-4 w-full" />
                </TableCell>
              </TableRow>
            ))}
          {!isLoading && (data?.records.length ?? 0) === 0 && (
            <TableRow>
              <TableCell colSpan={3} className="text-center text-sm text-muted-foreground">
                {t('attendance.noRecordsInRange')}
              </TableCell>
            </TableRow>
          )}
          {!isLoading &&
            data?.records.map((record) => (
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
