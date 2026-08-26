export interface AppNotification {
  id: number
  title: string
  body: string
  is_read: boolean
  read_at: string | null
  created_at: string
}
