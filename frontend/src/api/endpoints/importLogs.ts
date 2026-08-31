import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { ImportLog, ImportUndoResult } from '@/types/import'

export const importLogsApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<ImportLog>> => {
    const { data } = await httpClient.get<PaginatedResponse<ImportLog>>('/import-logs', { params })
    return data
  },
  /** What ImportForm polls while a queued (large-file) student import is still queued/processing — see ProcessStudentImportJob. */
  get: async (id: number): Promise<ImportLog> => {
    const { data } = await httpClient.get<ApiResponse<ImportLog>>(`/import-logs/${id}`)
    return data.data
  },
  undo: async (id: number): Promise<ImportUndoResult> => {
    const { data } = await httpClient.post<ApiResponse<ImportUndoResult>>(`/import-logs/${id}/undo`)
    return data.data
  },
}
