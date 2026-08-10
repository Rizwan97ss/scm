import { httpClient } from '@/api/client'
import type { ListQueryParams, PaginatedResponse } from '@/types/api'

export interface AuditLogEntry {
  id: number
  log_name: string | null
  description: string
  event: string | null
  subject_type: string | null
  subject_id: number | null
  causer: { id: number; full_name: string } | null
  changes: Record<string, unknown> | null
  created_at: string
}

export const auditLogsApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<AuditLogEntry>> => {
    const { data } = await httpClient.get<PaginatedResponse<AuditLogEntry>>('/audit-logs', { params })
    return data
  },
}
