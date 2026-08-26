import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { PlatformMetrics, PlatformSchool } from '@/types/platform'

export const platformApi = {
  listSchools: async (params?: ListQueryParams): Promise<PaginatedResponse<PlatformSchool>> => {
    const { data } = await httpClient.get<PaginatedResponse<PlatformSchool>>('/platform/schools', { params })
    return data
  },
  getSchool: async (id: number): Promise<PlatformSchool> => {
    const { data } = await httpClient.get<ApiResponse<PlatformSchool>>(`/platform/schools/${id}`)
    return data.data
  },
  changePlan: async (schoolId: number, planId: number): Promise<PlatformSchool> => {
    const { data } = await httpClient.post<ApiResponse<PlatformSchool>>(`/platform/schools/${schoolId}/plan`, { plan_id: planId })
    return data.data
  },
  metrics: async (): Promise<PlatformMetrics> => {
    const { data } = await httpClient.get<ApiResponse<PlatformMetrics>>('/platform/metrics')
    return data.data
  },
  offboardSchool: async (schoolId: number, mode: 'anonymize' | 'delete'): Promise<PlatformSchool | null> => {
    const { data } = await httpClient.post<ApiResponse<PlatformSchool | null>>(`/platform/schools/${schoolId}/offboard`, { mode })
    return data.data
  },
}
