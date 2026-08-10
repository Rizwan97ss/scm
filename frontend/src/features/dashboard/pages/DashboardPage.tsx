import { useQuery } from '@tanstack/react-query'
import { GraduationCap, Users, School, CalendarCheck, ClipboardList, Wallet, BookOpen, NotebookPen, Building2 } from 'lucide-react'
import { dashboardApi } from '@/api/endpoints/dashboard'
import { queryKeys } from '@/api/queryKeys'
import { useAuth } from '@/context/AuthContext'
import { StatCard } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { formatCurrency } from '@/utils/formatCurrency'
import type { AttendanceSummary } from '@/types/attendance'

export function DashboardPage() {
  const { user } = useAuth()
  const { data: summary, isLoading } = useQuery({
    queryKey: queryKeys.dashboardSummary,
    queryFn: dashboardApi.summary,
  })

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-xl font-semibold">Welcome back, {user?.first_name}</h1>
        <p className="text-sm text-muted-foreground">Here's what's happening today.</p>
      </div>

      {isLoading && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-28 w-full" />
          ))}
        </div>
      )}

      {!isLoading && summary?.role_context === 'super-admin' && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard label="Total Schools" value={String(summary.total_schools ?? 0)} icon={<Building2 className="h-5 w-5" />} />
          <StatCard label="Active / Trialing" value={String(summary.active_schools ?? 0)} icon={<School className="h-5 w-5" />} />
          <StatCard label="Currently Trialing" value={String(summary.trialing_schools ?? 0)} icon={<ClipboardList className="h-5 w-5" />} />
        </div>
      )}

      {!isLoading && summary?.role_context === 'staff' && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard label="Active Students" value={String(summary.student_count ?? 0)} icon={<GraduationCap className="h-5 w-5" />} />
          <StatCard label="Staff Members" value={String(summary.staff_count ?? 0)} icon={<Users className="h-5 w-5" />} />
          <StatCard label="Sections" value={String(summary.section_count ?? 0)} icon={<School className="h-5 w-5" />} />
          <StatCard label="Attendance Marked Today" value={String(summary.todays_attendance_marked_count ?? 0)} icon={<CalendarCheck className="h-5 w-5" />} />
          {summary.pending_leave_requests_count != null && (
            <StatCard label="Pending Leave Requests" value={String(summary.pending_leave_requests_count)} icon={<ClipboardList className="h-5 w-5" />} />
          )}
          {summary.fee_collected_this_month != null && (
            <StatCard label="Fees Collected This Month" value={formatCurrency(summary.fee_collected_this_month as number)} icon={<Wallet className="h-5 w-5" />} />
          )}
          {summary.library_overdue_count != null && (
            <StatCard label="Overdue Books" value={String(summary.library_overdue_count)} icon={<BookOpen className="h-5 w-5" />} />
          )}
        </div>
      )}

      {!isLoading && summary?.role_context === 'teacher' && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard label="Assigned Sections" value={String(summary.assigned_section_count ?? 0)} icon={<School className="h-5 w-5" />} />
          <StatCard label="Students" value={String(summary.student_count ?? 0)} icon={<GraduationCap className="h-5 w-5" />} />
          <StatCard label="Attendance Marked Today" value={String(summary.todays_attendance_marked_count ?? 0)} icon={<CalendarCheck className="h-5 w-5" />} />
          <StatCard label="Homework Awaiting Grading" value={String(summary.pending_homework_grading_count ?? 0)} icon={<NotebookPen className="h-5 w-5" />} />
        </div>
      )}

      {!isLoading && summary?.role_context === 'student' && (
        <>
          {(summary.attendance_this_month as AttendanceSummary | null) && (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <StatCard
                label="Attendance This Month"
                value={
                  (summary.attendance_this_month as AttendanceSummary).percentage !== null
                    ? `${(summary.attendance_this_month as AttendanceSummary).percentage}%`
                    : '—'
                }
                icon={<CalendarCheck className="h-5 w-5" />}
              />
              <StatCard label="Days Marked" value={String((summary.attendance_this_month as AttendanceSummary).total_marked)} />
              <StatCard label="Absences" value={String((summary.attendance_this_month as AttendanceSummary).counts.absent)} />
            </div>
          )}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard label="Homework Due" value={String(summary.pending_homework_count ?? 0)} icon={<NotebookPen className="h-5 w-5" />} />
            <StatCard label="Upcoming Exams" value={String(summary.upcoming_exam_count ?? 0)} icon={<ClipboardList className="h-5 w-5" />} />
          </div>
        </>
      )}

      {!isLoading && summary?.role_context === 'parent' && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard label="Linked Children" value={String(summary.children_count ?? 0)} icon={<GraduationCap className="h-5 w-5" />} />
          <StatCard label="Outstanding Fees" value={formatCurrency((summary.children_pending_fees_total as number) ?? 0)} icon={<Wallet className="h-5 w-5" />} />
        </div>
      )}
    </div>
  )
}
