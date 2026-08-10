export interface AttendanceTrendPoint {
  month: string
  percentage: number | null
}

export interface AttendanceBreakdown {
  overall_percentage: number | null
  trend: AttendanceTrendPoint[]
  by_section?: Record<string, number | null>
}

export interface AttendanceReport {
  from_date: string
  to_date: string
  student: AttendanceBreakdown | null
  staff: AttendanceBreakdown | null
}

export interface ExamPerformance {
  exam_id: number
  exam_name: string
  entries_count: number
  average_percentage: number | null
  pass_rate: number | null
}

export interface AcademicPerformanceReport {
  exams: ExamPerformance[]
}

export interface EnrollmentTrendPoint {
  month: string
  admissions: number
  withdrawals: number
  graduations: number
}

export interface EnrollmentReport {
  trend: EnrollmentTrendPoint[]
  active_by_grade: Record<string, number>
  active_total: number
}

export interface LibraryOperationsSummary {
  total_books: number
  issued_this_month: number
  currently_overdue: number
}

export interface TransportOperationsSummary {
  vehicle_count: number
  students_assigned: number
}

export interface HostelOperationsSummary {
  room_count: number
  total_capacity: number
  total_occupied: number
  occupancy_percentage: number | null
}

export interface OperationsReport {
  library: LibraryOperationsSummary | null
  transport: TransportOperationsSummary | null
  hostel: HostelOperationsSummary | null
}
