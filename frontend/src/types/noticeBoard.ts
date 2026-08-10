export const NOTICE_TYPES = ['general', 'event'] as const
export type NoticeTypeValue = (typeof NOTICE_TYPES)[number]
export const NOTICE_TYPE_LABELS: Record<NoticeTypeValue, string> = {
  general: 'Notice',
  event: 'Event',
}

export const AUDIENCES = ['all', 'students', 'staff', 'parents'] as const
export type AudienceValue = (typeof AUDIENCES)[number]
export const AUDIENCE_LABELS: Record<AudienceValue, string> = {
  all: 'Everyone',
  students: 'Students',
  staff: 'Staff',
  parents: 'Parents',
}

export interface Notice {
  id: number
  title: string
  body: string
  type: NoticeTypeValue
  type_label: string
  audience: AudienceValue
  audience_label: string
  event_date: string | null
  start_time: string | null
  end_time: string | null
  location: string | null
  is_published: boolean
  published_at: string | null
  expires_at: string | null
  created_by?: { id: number; full_name: string }
  created_at: string
}

export interface NoticePayload {
  title: string
  body: string
  type?: NoticeTypeValue
  audience?: AudienceValue
  event_date?: string | null
  start_time?: string | null
  end_time?: string | null
  location?: string | null
  expires_at?: string | null
}
