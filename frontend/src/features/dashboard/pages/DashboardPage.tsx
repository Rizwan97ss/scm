import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Area, AreaChart, Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import {
  GraduationCap,
  Users,
  School,
  CalendarCheck,
  ClipboardList,
  Wallet,
  BookOpen,
  NotebookPen,
  Building2,
  FileWarning,
  ClipboardPlus,
  Receipt,
  Megaphone,
  ArrowRight,
} from 'lucide-react'
import { dashboardApi } from '@/api/endpoints/dashboard'
import { queryKeys } from '@/api/queryKeys'
import { useAuth } from '@/context/AuthContext'
import { Card, CardContent, CardHeader, CardTitle, StatCard } from '@/components/ui/Card'
import { EmptyState } from '@/components/ui/EmptyState'
import { Skeleton } from '@/components/ui/Skeleton'
import { formatCurrency } from '@/utils/formatCurrency'
import { formatDate } from '@/utils/formatDate'
import { routePaths } from '@/routes/routePaths'
import type { AttendanceSummary } from '@/types/attendance'

function ViewAllLink({ to }: { to: string }) {
  const { t } = useTranslation()
  return (
    <Link to={to} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
      {t('dashboard.viewAll')} <ArrowRight className="h-3.5 w-3.5" />
    </Link>
  )
}

function QuickAction({ to, icon, label }: { to: string; icon: React.ReactNode; label: string }) {
  return (
    <Link
      to={to}
      className="flex items-center gap-3 rounded-lg border border-border bg-card p-4 transition-colors hover:border-primary/40 hover:bg-accent"
    >
      <span className="rounded-full bg-primary/10 p-2.5 text-primary">{icon}</span>
      <span className="text-sm font-medium">{label}</span>
    </Link>
  )
}

export function DashboardPage() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const { data: summary, isLoading } = useQuery({
    queryKey: queryKeys.dashboardSummary,
    queryFn: dashboardApi.summary,
  })

  const currency = 'USD'

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-xl font-semibold">{t('dashboard.welcomeBack', { name: user?.first_name })}</h1>
        <p className="text-sm text-muted-foreground">{t('dashboard.subtitle')}</p>
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
          <StatCard label="Total Schools" value={String(summary.total_schools ?? 0)} icon={<Building2 className="h-5 w-5" />} tone="primary" />
          <StatCard label="Active / Trialing" value={String(summary.active_schools ?? 0)} icon={<School className="h-5 w-5" />} tone="success" />
          <StatCard label="Currently Trialing" value={String(summary.trialing_schools ?? 0)} icon={<ClipboardList className="h-5 w-5" />} tone="warning" />
        </div>
      )}

      {!isLoading && summary?.role_context === 'staff' && (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label={t('dashboard.activeStudents')} value={String(summary.student_count ?? 0)} icon={<GraduationCap className="h-5 w-5" />} tone="primary" />
            <StatCard label={t('dashboard.staffMembers')} value={String(summary.staff_count ?? 0)} icon={<Users className="h-5 w-5" />} tone="violet" />
            <StatCard label={t('dashboard.sections')} value={String(summary.section_count ?? 0)} icon={<School className="h-5 w-5" />} tone="cyan" />
            <StatCard label={t('dashboard.attendanceToday')} value={String(summary.todays_attendance_marked_count ?? 0)} icon={<CalendarCheck className="h-5 w-5" />} tone="success" />
            {summary.pending_leave_requests_count != null && (
              <StatCard label={t('dashboard.pendingLeaveRequests')} value={String(summary.pending_leave_requests_count)} icon={<ClipboardList className="h-5 w-5" />} tone="warning" />
            )}
            {summary.fee_collected_this_month != null && (
              <StatCard
                label={t('dashboard.feesCollectedThisMonth')}
                value={formatCurrency(summary.fee_collected_this_month as number, currency)}
                icon={<Wallet className="h-5 w-5" />}
                tone="success"
              />
            )}
            {summary.outstanding_fees_total != null && (
              <StatCard
                label={t('dashboard.outstandingFees')}
                value={formatCurrency(summary.outstanding_fees_total, currency)}
                icon={<FileWarning className="h-5 w-5" />}
                tone="destructive"
              />
            )}
            {summary.library_overdue_count != null && (
              <StatCard label={t('dashboard.overdueBooks')} value={String(summary.library_overdue_count)} icon={<BookOpen className="h-5 w-5" />} tone="rose" />
            )}
          </div>

          <div>
            <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">{t('dashboard.quickActions')}</h2>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <QuickAction to={routePaths.attendanceTake} icon={<CalendarCheck className="h-4 w-4" />} label={t('dashboard.takeAttendance')} />
              <QuickAction to={routePaths.studentAdmission} icon={<ClipboardPlus className="h-4 w-4" />} label={t('dashboard.newAdmission')} />
              <QuickAction to={routePaths.invoices} icon={<Receipt className="h-4 w-4" />} label={t('dashboard.createInvoice')} />
              <QuickAction to={routePaths.noticeBoard} icon={<Megaphone className="h-4 w-4" />} label={t('dashboard.postAnnouncement')} />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {summary.attendance_trend && (
              <Card>
                <CardHeader>
                  <CardTitle>{t('dashboard.attendanceTrend')}</CardTitle>
                </CardHeader>
                <CardContent>
                  <ResponsiveContainer width="100%" height={220}>
                    <AreaChart data={summary.attendance_trend}>
                      <defs>
                        <linearGradient id="attendanceFill" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="0%" stopColor="var(--color-primary)" stopOpacity={0.25} />
                          <stop offset="100%" stopColor="var(--color-primary)" stopOpacity={0} />
                        </linearGradient>
                      </defs>
                      <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
                      <XAxis dataKey="label" fontSize={12} tickLine={false} axisLine={false} />
                      <YAxis domain={[0, 100]} fontSize={12} tickLine={false} axisLine={false} width={32} />
                      <Tooltip formatter={(value) => [value == null ? '—' : `${value}%`, t('dashboard.attendanceTrend')]} />
                      <Area type="monotone" dataKey="percentage" stroke="var(--color-primary)" strokeWidth={2} fill="url(#attendanceFill)" connectNulls />
                    </AreaChart>
                  </ResponsiveContainer>
                </CardContent>
              </Card>
            )}

            {summary.fee_trend && (
              <Card>
                <CardHeader>
                  <CardTitle>{t('dashboard.feeCollectionTrend')}</CardTitle>
                </CardHeader>
                <CardContent>
                  <ResponsiveContainer width="100%" height={220}>
                    <BarChart data={summary.fee_trend}>
                      <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
                      <XAxis dataKey="label" fontSize={12} tickLine={false} axisLine={false} />
                      <YAxis fontSize={12} tickLine={false} axisLine={false} width={40} />
                      <Tooltip formatter={(value) => [formatCurrency(value as number, currency), t('dashboard.feeCollectionTrend')]} />
                      <Bar dataKey="amount" fill="var(--color-success)" radius={[4, 4, 0, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                </CardContent>
              </Card>
            )}
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {summary.enrollment_trend && summary.enrollment_trend.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle>{t('dashboard.enrollmentTrend')}</CardTitle>
                </CardHeader>
                <CardContent>
                  <ResponsiveContainer width="100%" height={200}>
                    <AreaChart data={summary.enrollment_trend}>
                      <defs>
                        <linearGradient id="enrollmentFill" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="0%" stopColor="var(--color-info)" stopOpacity={0.25} />
                          <stop offset="100%" stopColor="var(--color-info)" stopOpacity={0} />
                        </linearGradient>
                      </defs>
                      <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
                      <XAxis dataKey="label" fontSize={12} tickLine={false} axisLine={false} />
                      <YAxis allowDecimals={false} fontSize={12} tickLine={false} axisLine={false} width={28} />
                      <Tooltip formatter={(value) => [value, t('dashboard.enrollmentTrend')]} />
                      <Area type="monotone" dataKey="count" stroke="var(--color-info)" strokeWidth={2} fill="url(#enrollmentFill)" />
                    </AreaChart>
                  </ResponsiveContainer>
                </CardContent>
              </Card>
            )}

            {summary.grade_distribution && summary.grade_distribution.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle>{t('dashboard.gradeDistribution')}</CardTitle>
                </CardHeader>
                <CardContent>
                  <ResponsiveContainer width="100%" height={Math.max(160, summary.grade_distribution.length * 32)}>
                    <BarChart data={summary.grade_distribution} layout="vertical" margin={{ left: 8 }}>
                      <CartesianGrid strokeDasharray="3 3" className="stroke-border" horizontal={false} />
                      <XAxis type="number" allowDecimals={false} fontSize={12} tickLine={false} axisLine={false} />
                      <YAxis type="category" dataKey="grade_level" fontSize={12} tickLine={false} axisLine={false} width={90} />
                      <Tooltip formatter={(value) => [value, t('dashboard.activeStudents')]} />
                      <Bar dataKey="count" fill="var(--color-primary)" radius={[0, 4, 4, 0]} />
                    </BarChart>
                  </ResponsiveContainer>
                </CardContent>
              </Card>
            )}
          </div>

          <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {summary.upcoming_exams && (
              <Card>
                <CardHeader className="flex-row items-center justify-between">
                  <CardTitle>{t('dashboard.upcomingExams')}</CardTitle>
                  <ViewAllLink to={routePaths.exams} />
                </CardHeader>
                <CardContent>
                  {summary.upcoming_exams.length === 0 ? (
                    <EmptyState title={t('dashboard.noUpcomingExams')} icon={<ClipboardList className="h-5 w-5" />} />
                  ) : (
                    <ul className="flex flex-col gap-3">
                      {summary.upcoming_exams.map((exam) => (
                        <li key={exam.id}>
                          <Link to={routePaths.examDetail(exam.id)} className="flex items-center justify-between gap-2 text-sm hover:text-primary">
                            <span className="font-medium">{exam.name}</span>
                            <span className="text-muted-foreground">{formatDate(exam.date)}</span>
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
                </CardContent>
              </Card>
            )}

            <Card>
              <CardHeader className="flex-row items-center justify-between">
                <CardTitle>{t('dashboard.recentAnnouncements')}</CardTitle>
                <ViewAllLink to={routePaths.noticeBoard} />
              </CardHeader>
              <CardContent>
                {!summary.recent_announcements || summary.recent_announcements.length === 0 ? (
                  <EmptyState title={t('dashboard.noAnnouncements')} icon={<Megaphone className="h-5 w-5" />} />
                ) : (
                  <ul className="flex flex-col gap-3">
                    {summary.recent_announcements.map((announcement) => (
                      <li key={announcement.id}>
                        <Link to={routePaths.noticeBoard} className="flex items-center justify-between gap-2 text-sm hover:text-primary">
                          <span className="font-medium">{announcement.title}</span>
                          <span className="text-muted-foreground">{formatDate(announcement.sent_at)}</span>
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>

            {summary.pending_leave_requests && (
              <Card>
                <CardHeader className="flex-row items-center justify-between">
                  <CardTitle>{t('dashboard.pendingLeaveRequests')}</CardTitle>
                  <ViewAllLink to={routePaths.leaveRequests} />
                </CardHeader>
                <CardContent>
                  {summary.pending_leave_requests.length === 0 ? (
                    <EmptyState title={t('dashboard.noPendingLeave')} icon={<ClipboardList className="h-5 w-5" />} />
                  ) : (
                    <ul className="flex flex-col gap-3">
                      {summary.pending_leave_requests.map((leave) => (
                        <li key={leave.id}>
                          <Link to={routePaths.leaveRequests} className="flex items-center justify-between gap-2 text-sm hover:text-primary">
                            <span className="font-medium">{leave.staff_name}</span>
                            <span className="text-muted-foreground">
                              {formatDate(leave.from)} – {formatDate(leave.to)}
                            </span>
                          </Link>
                        </li>
                      ))}
                    </ul>
                  )}
                </CardContent>
              </Card>
            )}
          </div>
        </>
      )}

      {!isLoading && summary?.role_context === 'teacher' && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard label={t('dashboard.assignedSections')} value={String(summary.assigned_section_count ?? 0)} icon={<School className="h-5 w-5" />} tone="cyan" />
          <StatCard label={t('dashboard.activeStudents')} value={String(summary.student_count ?? 0)} icon={<GraduationCap className="h-5 w-5" />} tone="primary" />
          <StatCard label={t('dashboard.attendanceToday')} value={String(summary.todays_attendance_marked_count ?? 0)} icon={<CalendarCheck className="h-5 w-5" />} tone="success" />
          <StatCard label={t('dashboard.homeworkAwaitingGrading')} value={String(summary.pending_homework_grading_count ?? 0)} icon={<NotebookPen className="h-5 w-5" />} tone="warning" />
        </div>
      )}

      {!isLoading && summary?.role_context === 'student' && (
        <>
          {(summary.attendance_this_month as AttendanceSummary | null) && (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <StatCard
                label={t('dashboard.attendanceThisMonth')}
                value={
                  (summary.attendance_this_month as AttendanceSummary).percentage !== null
                    ? `${(summary.attendance_this_month as AttendanceSummary).percentage}%`
                    : '—'
                }
                icon={<CalendarCheck className="h-5 w-5" />}
                tone="success"
              />
              <StatCard label={t('dashboard.daysMarked')} value={String((summary.attendance_this_month as AttendanceSummary).total_marked)} tone="cyan" />
              <StatCard label={t('dashboard.absences')} value={String((summary.attendance_this_month as AttendanceSummary).counts.absent)} tone="destructive" />
            </div>
          )}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard label={t('dashboard.homeworkDue')} value={String(summary.pending_homework_count ?? 0)} icon={<NotebookPen className="h-5 w-5" />} tone="warning" />
            <StatCard label={t('dashboard.upcomingExams')} value={String(summary.upcoming_exam_count ?? 0)} icon={<ClipboardList className="h-5 w-5" />} tone="violet" />
          </div>
        </>
      )}

      {!isLoading && summary?.role_context === 'parent' && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard label={t('dashboard.linkedChildren')} value={String(summary.children_count ?? 0)} icon={<GraduationCap className="h-5 w-5" />} tone="primary" />
          <StatCard label={t('dashboard.outstandingFees')} value={formatCurrency((summary.children_pending_fees_total as number) ?? 0, currency)} icon={<Wallet className="h-5 w-5" />} tone="destructive" />
        </div>
      )}
    </div>
  )
}
