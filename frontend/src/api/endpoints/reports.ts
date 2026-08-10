import { httpClient } from '@/api/client'
import type { ApiResponse } from '@/types/api'
import type { AcademicPerformanceReport, AttendanceReport, EnrollmentReport, OperationsReport } from '@/types/reports'

export const reportsApi = {
  attendance: async (params?: { from_date?: string; to_date?: string }): Promise<AttendanceReport> => {
    const { data } = await httpClient.get<ApiResponse<AttendanceReport>>('/reports/attendance', { params })
    return data.data
  },
  academicPerformance: async (): Promise<AcademicPerformanceReport> => {
    const { data } = await httpClient.get<ApiResponse<AcademicPerformanceReport>>('/reports/academic-performance')
    return data.data
  },
  enrollment: async (): Promise<EnrollmentReport> => {
    const { data } = await httpClient.get<ApiResponse<EnrollmentReport>>('/reports/enrollment')
    return data.data
  },
  operations: async (): Promise<OperationsReport> => {
    const { data } = await httpClient.get<ApiResponse<OperationsReport>>('/reports/operations')
    return data.data
  },
}
