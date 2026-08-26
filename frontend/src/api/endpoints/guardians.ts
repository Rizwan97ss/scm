import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { Guardian } from '@/types/student'

export const guardiansApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<Guardian>> => {
    const { data } = await httpClient.get<PaginatedResponse<Guardian>>('/guardians', { params })
    return data
  },
  get: async (id: number): Promise<Guardian> => {
    const { data } = await httpClient.get<ApiResponse<Guardian>>(`/guardians/${id}`)
    return data.data
  },
  invite: async (id: number): Promise<void> => {
    await httpClient.post(`/guardians/${id}/invite`)
  },
}
