import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse, PaginationMeta } from '@/types/api'
import type { Announcement, AnnouncementPayload } from '@/types/communication'
import type { AppNotification } from '@/types/notifications'

export const announcementsApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<Announcement>> => {
    const { data } = await httpClient.get<PaginatedResponse<Announcement>>('/announcements', { params })
    return data
  },
  send: async (payload: AnnouncementPayload): Promise<Announcement> => {
    const { data } = await httpClient.post<ApiResponse<Announcement>>('/announcements', payload)
    return data.data
  },
}

export interface NotificationListMeta extends PaginationMeta {
  unread_count: number
}

export interface NotificationListResponse extends ApiResponse<AppNotification[]> {
  meta: NotificationListMeta
}

export const notificationsApi = {
  list: async (params?: ListQueryParams): Promise<NotificationListResponse> => {
    const { data } = await httpClient.get<NotificationListResponse>('/notifications', { params })
    return data
  },
  markRead: async (id: number): Promise<AppNotification> => {
    const { data } = await httpClient.post<ApiResponse<AppNotification>>(`/notifications/${id}/read`)
    return data.data
  },
  markAllRead: async (): Promise<void> => {
    await httpClient.post('/notifications/read-all')
  },
}
