import { httpClient } from '@/api/client'
import { apiFileUrl } from '@/utils/apiFileUrl'
import type { ApiResponse } from '@/types/api'
import type { AttendanceSummary, StudentAttendanceRecord } from '@/types/attendance'
import type { Exam, ReportCard, TermResult } from '@/types/exam'
import type { FeeStatement } from '@/types/fees'
import type { Homework, StudentRemark } from '@/types/homework'
import type { Student } from '@/types/student'

export interface AttendanceTrendPoint {
  date: string
  label: string
  percentage: number | null
}

export interface FeeTrendPoint {
  month: string
  label: string
  amount: number
}

export interface EnrollmentTrendPoint {
  month: string
  label: string
  count: number
}

export interface GradeDistributionPoint {
  grade_level: string
  count: number
}

export interface UpcomingExamSummary {
  id: number
  name: string
  date: string
}

export interface RecentAnnouncementSummary {
  id: number
  title: string
  sent_at: string | null
}

export interface PendingLeaveRequestSummary {
  id: number
  staff_name: string
  leave_type: string
  from: string
  to: string
}

export interface OutstandingInvoiceRow {
  id: number
  invoice_number: string
  student_name: string | null
  balance: number
  due_date: string | null
}

export interface OutstandingInvoicesSummary {
  total_outstanding: number
  overdue_count: number
  top: OutstandingInvoiceRow[]
}

export interface FeeCollectionThisMonth {
  from_date: string
  to_date: string
  total_collected: number
  payment_count: number
  by_method: Record<string, number>
  by_category: Record<string, number>
}

export interface LibraryDueSoonRow {
  id: number
  book_title: string
  borrower_name: string | null
  due_date: string
}

export interface TransportSummary {
  vehicle_count: number
  students_assigned: number
}

export interface TransportAssignmentRow {
  id: number
  student_name: string
  route: string | null
  vehicle: string | null
}

export interface HostelSummary {
  room_count: number
  total_capacity: number
  total_occupied: number
  occupancy_percentage: number | null
}

export interface PayrollSummary {
  paid_count: number
  pending_count: number
  total_net: number
}

export interface VisitorsTodaySummary {
  total_today: number
  checked_in_now: number
}

export interface RecentExamPerformanceRow {
  exam_id: number
  exam_name: string
  entries_count: number
  average_percentage: number | null
  pass_rate: number | null
}

export interface SectionTodayAttendance {
  section_id: number
  section_name: string
  summary: AttendanceSummary
}

export interface StudentRecentGrade {
  exam_name: string | null
  subject: string | null
  percentage: number | null
  grade_label: string | null
}

export interface ParentChildSummary {
  id: number
  name: string
  grade_level: string | null
  section: string | null
  attendance_percentage: number | null
  pending_fees: number
  upcoming_exam_count: number
}

export interface DashboardSummary {
  role_context: 'staff' | 'teacher' | 'student' | 'parent' | 'super-admin'
  outstanding_fees_total?: number | null
  attendance_trend?: AttendanceTrendPoint[] | null
  fee_trend?: FeeTrendPoint[] | null
  enrollment_trend?: EnrollmentTrendPoint[]
  grade_distribution?: GradeDistributionPoint[]
  upcoming_exams?: UpcomingExamSummary[] | null
  recent_announcements?: RecentAnnouncementSummary[]
  pending_leave_requests?: PendingLeaveRequestSummary[] | null
  fee_collection_this_month?: FeeCollectionThisMonth | null
  outstanding_invoices?: OutstandingInvoicesSummary | null
  library_due_soon?: LibraryDueSoonRow[] | null
  transport_summary?: TransportSummary | null
  transport_today_assignments?: TransportAssignmentRow[] | null
  hostel_summary?: HostelSummary | null
  payroll_summary?: PayrollSummary | null
  visitors_today?: VisitorsTodaySummary | null
  recent_exam_performance?: RecentExamPerformanceRow[] | null
  section_today?: SectionTodayAttendance[] | null
  recent_grades?: StudentRecentGrade[] | null
  children?: ParentChildSummary[]
  [key: string]: unknown
}

export const dashboardApi = {
  summary: async (): Promise<DashboardSummary> => {
    const { data } = await httpClient.get<ApiResponse<DashboardSummary>>('/dashboard/summary')
    return data.data
  },
}

export const parentPortalApi = {
  children: async (): Promise<Student[]> => {
    const { data } = await httpClient.get<ApiResponse<Student[]>>('/parent/children')
    return data.data
  },
  childProfile: async (studentId: number): Promise<Student> => {
    const { data } = await httpClient.get<ApiResponse<Student>>(`/parent/children/${studentId}/profile`)
    return data.data
  },
  childAttendance: async (
    studentId: number,
    params?: { from?: string; to?: string }
  ): Promise<{ summary: AttendanceSummary; records: StudentAttendanceRecord[] }> => {
    const { data } = await httpClient.get<ApiResponse<{ summary: AttendanceSummary; records: StudentAttendanceRecord[] }>>(
      `/parent/children/${studentId}/attendance`,
      { params }
    )
    return data.data
  },
  childExams: async (studentId: number): Promise<Exam[]> => {
    const { data } = await httpClient.get<ApiResponse<Exam[]>>(`/parent/children/${studentId}/exams`)
    return data.data
  },
  childReportCard: async (studentId: number, examId: number): Promise<ReportCard> => {
    const { data } = await httpClient.get<ApiResponse<ReportCard>>(`/parent/children/${studentId}/report-card`, { params: { exam_id: examId } })
    return data.data
  },
  childReportCardPdfUrl: (studentId: number, examId: number) => apiFileUrl(`/parent/children/${studentId}/report-card/pdf?exam_id=${examId}`),
  childTermResult: async (studentId: number, termId: number): Promise<TermResult> => {
    const { data } = await httpClient.get<ApiResponse<TermResult>>(`/parent/children/${studentId}/term-result`, { params: { term_id: termId } })
    return data.data
  },
  childTermResultPdfUrl: (studentId: number, termId: number) => apiFileUrl(`/parent/children/${studentId}/term-result/pdf?term_id=${termId}`),
  childHomework: async (studentId: number): Promise<Homework[]> => {
    const { data } = await httpClient.get<ApiResponse<Homework[]>>(`/parent/children/${studentId}/homework`)
    return data.data
  },
  childRemarks: async (studentId: number): Promise<StudentRemark[]> => {
    const { data } = await httpClient.get<ApiResponse<StudentRemark[]>>(`/parent/children/${studentId}/remarks`)
    return data.data
  },
  childInvoices: async (studentId: number): Promise<FeeStatement> => {
    const { data } = await httpClient.get<ApiResponse<FeeStatement>>(`/parent/children/${studentId}/invoices`)
    return data.data
  },
}
