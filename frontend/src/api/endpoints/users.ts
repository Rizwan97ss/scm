import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { UserStatus } from '@/types/enums'
import type { User as UserModel } from '@/types/auth'
import type { ImportResult } from '@/types/import'

export interface CreateUserPayload {
  first_name: string
  last_name: string
  email: string
  username?: string | null
  phone?: string | null
  gender?: string | null
  date_of_birth?: string | null
  password: string
  password_confirmation: string
  roles: string[]
  designation_id?: number | null
  employee_id?: string | null
  hire_date?: string | null
}

export type UpdateUserPayload = Partial<
  Pick<
    CreateUserPayload,
    'first_name' | 'last_name' | 'email' | 'username' | 'phone' | 'gender' | 'date_of_birth' | 'designation_id' | 'employee_id' | 'hire_date'
  >
>

export const usersApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<UserModel>> => {
    const { data } = await httpClient.get<PaginatedResponse<UserModel>>('/users', { params })
    return data
  },
  get: async (id: number): Promise<UserModel> => {
    const { data } = await httpClient.get<ApiResponse<UserModel>>(`/users/${id}`)
    return data.data
  },
  create: async (payload: CreateUserPayload): Promise<UserModel> => {
    const { data } = await httpClient.post<ApiResponse<UserModel>>('/users', payload)
    return data.data
  },
  update: async (id: number, payload: UpdateUserPayload): Promise<UserModel> => {
    const { data } = await httpClient.put<ApiResponse<UserModel>>(`/users/${id}`, payload)
    return data.data
  },
  remove: async (id: number): Promise<void> => {
    await httpClient.delete(`/users/${id}`)
  },
  updateRoles: async (id: number, roles: string[]): Promise<UserModel> => {
    const { data } = await httpClient.post<ApiResponse<UserModel>>(`/users/${id}/roles`, { roles })
    return data.data
  },
  updateStatus: async (id: number, status: UserStatus): Promise<UserModel> => {
    const { data } = await httpClient.post<ApiResponse<UserModel>>(`/users/${id}/status`, { status })
    return data.data
  },
  resetPassword: async (id: number): Promise<void> => {
    await httpClient.post(`/users/${id}/reset-password`)
  },
  resetMfa: async (id: number): Promise<void> => {
    await httpClient.post(`/users/${id}/mfa/reset`)
  },
  exportUrl: '/users/export',
  importTemplateUrl: '/users/import/template',
  import: async (file: File, dryRun = false): Promise<ImportResult> => {
    const formData = new FormData()
    formData.append('file', file)
    if (dryRun) formData.append('dry_run', '1')
    const { data } = await httpClient.post<ApiResponse<ImportResult>>('/users/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },
}
