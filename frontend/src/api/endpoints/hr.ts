import { httpClient } from '@/api/client'
import { createCrudEndpoints } from './crudFactory'
import { apiFileUrl } from '@/utils/apiFileUrl'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type {
  Designation,
  DesignationPayload,
  GeneratePayrollPayload,
  GeneratePayrollResult,
  LeaveRequest,
  LeaveRequestPayload,
  LeaveType,
  LeaveTypePayload,
  Payslip,
  SalaryStructure,
  SalaryStructurePayload,
} from '@/types/hr'

export const designationsApi = createCrudEndpoints<Designation, DesignationPayload>('designations')

export const leaveTypesApi = createCrudEndpoints<LeaveType, LeaveTypePayload>('leave-types')

export const leaveRequestsApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<LeaveRequest>> => {
    const { data } = await httpClient.get<PaginatedResponse<LeaveRequest>>('/leave-requests', { params })
    return data
  },
  get: async (id: number): Promise<LeaveRequest> => {
    const { data } = await httpClient.get<ApiResponse<LeaveRequest>>(`/leave-requests/${id}`)
    return data.data
  },
  apply: async (payload: LeaveRequestPayload): Promise<LeaveRequest> => {
    const { data } = await httpClient.post<ApiResponse<LeaveRequest>>('/leave-requests', payload)
    return data.data
  },
  cancel: async (id: number): Promise<LeaveRequest> => {
    const { data } = await httpClient.post<ApiResponse<LeaveRequest>>(`/leave-requests/${id}/cancel`)
    return data.data
  },
  review: async (id: number, payload: { status: 'approved' | 'rejected'; review_notes?: string | null }): Promise<LeaveRequest> => {
    const { data } = await httpClient.post<ApiResponse<LeaveRequest>>(`/leave-requests/${id}/review`, payload)
    return data.data
  },
}

export const salaryStructuresApi = createCrudEndpoints<SalaryStructure, SalaryStructurePayload>('salary-structures')

export const payslipsApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<Payslip>> => {
    const { data } = await httpClient.get<PaginatedResponse<Payslip>>('/payslips', { params })
    return data
  },
  generate: async (payload: GeneratePayrollPayload): Promise<GeneratePayrollResult> => {
    const { data } = await httpClient.post<ApiResponse<GeneratePayrollResult>>('/payslips/generate', payload)
    return data.data
  },
  markPaid: async (id: number): Promise<Payslip> => {
    const { data } = await httpClient.post<ApiResponse<Payslip>>(`/payslips/${id}/mark-paid`)
    return data.data
  },
  receiptPdfUrl: (id: number) => apiFileUrl(`/payslips/${id}/receipt/pdf`),
}
