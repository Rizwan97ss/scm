import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { Notice, NoticePayload } from '@/types/noticeBoard'

export const noticesApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<Notice>> => {
    const { data } = await httpClient.get<PaginatedResponse<Notice>>('/notices', { params })
    return data
  },
  get: async (id: number): Promise<Notice> => {
    const { data } = await httpClient.get<ApiResponse<Notice>>(`/notices/${id}`)
    return data.data
  },
  create: async (payload: NoticePayload): Promise<Notice> => {
    const { data } = await httpClient.post<ApiResponse<Notice>>('/notices', payload)
    return data.data
  },
  update: async (id: number, payload: Partial<NoticePayload>): Promise<Notice> => {
    const { data } = await httpClient.put<ApiResponse<Notice>>(`/notices/${id}`, payload)
    return data.data
  },
  remove: async (id: number): Promise<void> => {
    await httpClient.delete(`/notices/${id}`)
  },
  publish: async (id: number): Promise<Notice> => {
    const { data } = await httpClient.post<ApiResponse<Notice>>(`/notices/${id}/publish`)
    return data.data
  },
}
