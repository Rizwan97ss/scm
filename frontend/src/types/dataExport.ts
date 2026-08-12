export interface DataExport {
  id: number
  scope: 'self' | 'school'
  status: 'pending' | 'processing' | 'ready' | 'failed'
  requested_by: string | null
  expires_at: string | null
  failure_reason: string | null
  created_at: string
}
