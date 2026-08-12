import { httpClient } from '@/api/client'
import { apiFileUrl } from '@/utils/apiFileUrl'
import type { ApiResponse } from '@/types/api'
import type { DataExport } from '@/types/dataExport'

export const dataExportsApi = {
  listSelf: async (): Promise<DataExport[]> => {
    const { data } = await httpClient.get<ApiResponse<DataExport[]>>('/account/data-export')
    return data.data
  },
  requestSelf: async (): Promise<DataExport> => {
    const { data } = await httpClient.post<ApiResponse<DataExport>>('/account/data-export')
    return data.data
  },
  listSchool: async (): Promise<DataExport[]> => {
    const { data } = await httpClient.get<ApiResponse<DataExport[]>>('/data-exports')
    return data.data
  },
  requestSchool: async (): Promise<DataExport> => {
    const { data } = await httpClient.post<ApiResponse<DataExport>>('/data-exports')
    return data.data
  },
  downloadUrl: (id: number): string => apiFileUrl(`/data-exports/${id}/download`),
}
