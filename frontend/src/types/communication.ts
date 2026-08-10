import type { AudienceValue } from './noticeBoard'

export const ANNOUNCEMENT_CHANNELS = ['in_app', 'email', 'sms', 'push'] as const
export type AnnouncementChannel = (typeof ANNOUNCEMENT_CHANNELS)[number]
export const ANNOUNCEMENT_CHANNEL_LABELS: Record<AnnouncementChannel, string> = {
  in_app: 'In-App',
  email: 'Email',
  sms: 'SMS',
  push: 'Push',
}

export interface Announcement {
  id: number
  title: string
  body: string
  audience: AudienceValue
  audience_label: string
  channels: AnnouncementChannel[]
  recipient_count: number
  sent_by?: { id: number; full_name: string }
  sent_at: string
}

export interface AnnouncementPayload {
  title: string
  body: string
  audience: AudienceValue
  channels: AnnouncementChannel[]
}
