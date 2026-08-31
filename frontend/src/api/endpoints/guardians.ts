import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { Guardian } from '@/types/student'
import type { ImportMode, ImportResult } from '@/types/import'

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
  importTemplateUrl: '/guardians/import/template',
  import: async (file: File, dryRun = false, mode: ImportMode = 'create'): Promise<ImportResult> => {
    const formData = new FormData()
    formData.append('file', file)
    if (dryRun) formData.append('dry_run', '1')
    formData.append('mode', mode)
    const { data } = await httpClient.post<ApiResponse<ImportResult>>('/guardians/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },
}
