import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { Visitor, VisitorPayload } from '@/types/frontDesk'

export const visitorsApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<Visitor>> => {
    const { data } = await httpClient.get<PaginatedResponse<Visitor>>('/visitors', { params })
    return data
  },
  checkIn: async (payload: VisitorPayload): Promise<Visitor> => {
    const { data } = await httpClient.post<ApiResponse<Visitor>>('/visitors', payload)
    return data.data
  },
  checkOut: async (id: number): Promise<Visitor> => {
    const { data } = await httpClient.post<ApiResponse<Visitor>>(`/visitors/${id}/check-out`)
    return data.data
  },
}
