import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CalendarCheck, Save } from 'lucide-react'
import { sectionsApi, timetablePeriodsApi } from '@/api/endpoints/academics'
import { studentAttendanceApi } from '@/api/endpoints/attendance'
import { studentsApi } from '@/api/endpoints/students'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DatePicker, EmptyState, Input, Select, Skeleton, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { AttendanceStatusPicker } from '../components/AttendanceStatusPicker'
import { ATTENDANCE_STATUS_BADGE_VARIANT } from '../statusStyles'
import { ATTENDANCE_STATUSES, ATTENDANCE_STATUS_LABELS, type AttendanceStatus } from '@/types/enums'
import type { ApiError } from '@/api/client'

const WHOLE_DAY_VALUE = 'whole-day'

interface EntryState {
  status: AttendanceStatus
  remarks: string
}

export function TakeAttendancePage() {
  const queryClient = useQueryClient()
  const [sectionId, setSectionId] = useState<number | null>(null)
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [periodId, setPeriodId] = useState<number | null>(null)
  const [entries, setEntries] = useState<Record<number, EntryState>>({})

  const { data: sections } = useQuery({ queryKey: queryKeys.sections({ per_page: 200 }), queryFn: () => sectionsApi.list({ per_page: 200 }) })
  const { data: periods } = useQuery({ queryKey: queryKeys.timetablePeriods({ per_page: 100 }), queryFn: () => timetablePeriodsApi.list({ per_page: 100 }) })

  const { data: roster, isLoading: rosterLoading } = useQuery({
    queryKey: queryKeys.students({ 'filter[current_section_id]': sectionId, per_page: 200 }),
    queryFn: () => studentsApi.list({ 'filter[current_section_id]': sectionId!, per_page: 200 }),
    enabled: sectionId !== null,
  })

  const { data: existing } = useQuery({
    queryKey: queryKeys.studentAttendance({ 'filter[section_id]': sectionId, 'filter[date]': date }),
    queryFn: () => studentAttendanceApi.list({ 'filter[section_id]': sectionId!, 'filter[date]': date, per_page: 200 }),
    enabled: sectionId !== null && !!date,
  })

  // Rebuild the working entries whenever the roster or an already-saved record
  // for this exact section/date/period comes back, so re-opening a previously
  // marked day shows what was actually saved instead of defaulting everyone
  // back to Present.
  useEffect(() => {
    if (!roster) return
    const existingForPeriod = new Map((existing?.data ?? []).filter((r) => (r.timetable_period_id ?? null) === periodId).map((r) => [r.student_id, r]))
    const next: Record<number, EntryState> = {}
    for (const student of roster.data) {
      const record = existingForPeriod.get(student.id)
      next[student.id] = { status: record?.status ?? 'present', remarks: record?.remarks ?? '' }
    }
    setEntries(next)
  }, [roster, existing, periodId])

  const tally = useMemo(() => {
    const counts = Object.fromEntries(ATTENDANCE_STATUSES.map((s) => [s, 0])) as Record<AttendanceStatus, number>
    Object.values(entries).forEach((e) => counts[e.status]++)
    return counts
  }, [entries])

  const markMutation = useMutation({
    mutationFn: () =>
      studentAttendanceApi.mark({
        section_id: sectionId!,
        date,
        timetable_period_id: periodId,
        entries: Object.entries(entries).map(([studentId, e]) => ({
          student_id: Number(studentId),
          status: e.status,
          remarks: e.remarks || null,
        })),
      }),
    onSuccess: (records) => {
      toast.success(`${records.length} attendance record(s) saved.`)
      queryClient.invalidateQueries({ queryKey: queryKeys.studentAttendance() })
      queryClient.invalidateQueries({ queryKey: queryKeys.dashboardSummary })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <div>
      <PageHeader title="Take Attendance" description="Mark daily or period-based attendance for a section." />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
          <label className="mb-1 block text-sm font-medium">Section</label>
          <Select
            value={sectionId ? String(sectionId) : undefined}
            onValueChange={(value) => setSectionId(Number(value))}
            options={(sections?.data ?? []).map((section) => ({
              value: String(section.id),
              label: `${section.grade_level?.name ?? ''} - ${section.name}`,
            }))}
            placeholder="Select a section…"
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium">Date</label>
          <DatePicker value={date} onChange={(e) => setDate(e.target.value)} />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium">Period</label>
          <Select
            value={periodId ? String(periodId) : WHOLE_DAY_VALUE}
            onValueChange={(value) => setPeriodId(value === WHOLE_DAY_VALUE ? null : Number(value))}
            options={[
              { value: WHOLE_DAY_VALUE, label: 'Whole Day' },
              ...(periods?.data ?? []).filter((p) => !p.is_break).map((p) => ({ value: String(p.id), label: p.name })),
            ]}
          />
        </div>
      </div>

      {sectionId === null && (
        <EmptyState icon={<CalendarCheck className="h-6 w-6" />} title="Select a section to begin" description="Choose a section, date, and period above to load its roster." />
      )}

      {sectionId !== null && rosterLoading && (
        <div className="flex flex-col gap-2">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-12 w-full" />
          ))}
        </div>
      )}

      {sectionId !== null && !rosterLoading && roster?.data.length === 0 && (
        <EmptyState title="No students in this section" description="This section has no students currently enrolled." />
      )}

      {sectionId !== null && !rosterLoading && (roster?.data.length ?? 0) > 0 && (
        <>
          <div className="mb-4 flex flex-wrap items-center gap-2">
            {ATTENDANCE_STATUSES.map((status) => (
              <Badge key={status} variant={ATTENDANCE_STATUS_BADGE_VARIANT[status]}>
                {ATTENDANCE_STATUS_LABELS[status]}: {tally[status]}
              </Badge>
            ))}
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Admission #</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Remarks</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {roster!.data.map((student) => (
                <TableRow key={student.id}>
                  <TableCell className="text-muted-foreground">{student.admission_number}</TableCell>
                  <TableCell className="font-medium">{student.full_name}</TableCell>
                  <TableCell>
                    <AttendanceStatusPicker
                      value={entries[student.id]?.status ?? 'present'}
                      onChange={(status) => setEntries((prev) => ({ ...prev, [student.id]: { ...prev[student.id], status, remarks: prev[student.id]?.remarks ?? '' } }))}
                    />
                  </TableCell>
                  <TableCell>
                    <Input
                      className="h-8 w-48"
                      placeholder="Optional remarks"
                      value={entries[student.id]?.remarks ?? ''}
                      onChange={(e) => setEntries((prev) => ({ ...prev, [student.id]: { ...prev[student.id], status: prev[student.id]?.status ?? 'present', remarks: e.target.value } }))}
                    />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          <div className="mt-4 flex justify-end">
            <Button onClick={() => markMutation.mutate()} isLoading={markMutation.isPending}>
              <Save className="h-4 w-4" /> Save Attendance
            </Button>
          </div>
        </>
      )}
    </div>
  )
}
