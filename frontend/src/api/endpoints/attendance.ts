import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type {
  AttendanceSummary,
  CorrectAttendancePayload,
  MarkStaffAttendancePayload,
  MarkStudentAttendancePayload,
  StaffAttendanceRecord,
  StudentAttendanceRecord,
} from '@/types/attendance'

export const studentAttendanceApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<StudentAttendanceRecord>> => {
    const { data } = await httpClient.get<PaginatedResponse<StudentAttendanceRecord>>('/attendance/students', { params })
    return data
  },
  mark: async (payload: MarkStudentAttendancePayload): Promise<StudentAttendanceRecord[]> => {
    const { data } = await httpClient.post<ApiResponse<StudentAttendanceRecord[]>>('/attendance/students', payload)
    return data.data
  },
  correct: async (id: number, payload: CorrectAttendancePayload): Promise<StudentAttendanceRecord> => {
    const { data } = await httpClient.put<ApiResponse<StudentAttendanceRecord>>(`/attendance/students/${id}`, payload)
    return data.data
  },
  summary: async (params: { student_id: number; from?: string; to?: string }): Promise<AttendanceSummary> => {
    const { data } = await httpClient.get<ApiResponse<AttendanceSummary>>('/attendance/students/summary', { params })
    return data.data
  },
  sectionSummary: async (params: { section_id: number; date?: string; timetable_period_id?: number }): Promise<AttendanceSummary> => {
    const { data } = await httpClient.get<ApiResponse<AttendanceSummary>>('/attendance/students/section-summary', { params })
    return data.data
  },
}

export const staffAttendanceApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<StaffAttendanceRecord>> => {
    const { data } = await httpClient.get<PaginatedResponse<StaffAttendanceRecord>>('/attendance/staff', { params })
    return data
  },
  mark: async (payload: MarkStaffAttendancePayload): Promise<StaffAttendanceRecord[]> => {
    const { data } = await httpClient.post<ApiResponse<StaffAttendanceRecord[]>>('/attendance/staff', payload)
    return data.data
  },
  correct: async (id: number, payload: CorrectAttendancePayload): Promise<StaffAttendanceRecord> => {
    const { data } = await httpClient.put<ApiResponse<StaffAttendanceRecord>>(`/attendance/staff/${id}`, payload)
    return data.data
  },
  checkIn: async (): Promise<StaffAttendanceRecord> => {
    const { data } = await httpClient.post<ApiResponse<StaffAttendanceRecord>>('/attendance/staff/check-in')
    return data.data
  },
  checkOut: async (): Promise<StaffAttendanceRecord> => {
    const { data } = await httpClient.post<ApiResponse<StaffAttendanceRecord>>('/attendance/staff/check-out')
    return data.data
  },
  summary: async (params?: { user_id?: number; from?: string; to?: string }): Promise<AttendanceSummary> => {
    const { data } = await httpClient.get<ApiResponse<AttendanceSummary>>('/attendance/staff/summary', { params })
    return data.data
  },
}
