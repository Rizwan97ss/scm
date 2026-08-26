import type { AttendanceStatus } from './enums'

export interface StudentAttendanceRecord {
  id: number
  student_id: number
  student?: { id: number; full_name: string; admission_number: string }
  section_id: number
  section?: { id: number; name: string } | null
  academic_year_id: number
  timetable_period_id: number | null
  period?: { id: number; name: string } | null
  date: string
  status: AttendanceStatus
  status_label: string
  remarks: string | null
  marked_by?: { id: number; full_name: string } | null
  created_at: string
  updated_at: string
}

export interface StaffAttendanceRecord {
  id: number
  user_id: number
  user?: { id: number; full_name: string; roles: string[] }
  date: string
  status: AttendanceStatus
  status_label: string
  check_in_time: string | null
  check_out_time: string | null
  remarks: string | null
  marked_by?: { id: number; full_name: string } | null
  created_at: string
  updated_at: string
}

export interface AttendanceSummary {
  total_marked: number
  present_equivalent: number
  percentage: number | null
  counts: Record<AttendanceStatus, number>
}

export interface MarkStudentAttendanceEntry {
  student_id: number
  status: AttendanceStatus
  remarks?: string | null
}

export interface MarkStudentAttendancePayload {
  section_id: number
  date: string
  timetable_period_id?: number | null
  entries: MarkStudentAttendanceEntry[]
}

export interface MarkStaffAttendanceEntry {
  user_id: number
  status: AttendanceStatus
  remarks?: string | null
}

export interface MarkStaffAttendancePayload {
  date: string
  entries: MarkStaffAttendanceEntry[]
}

export interface CorrectAttendancePayload {
  status: AttendanceStatus
  remarks?: string | null
}
