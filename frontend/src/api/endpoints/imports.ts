import { httpClient } from '@/api/client'
import type { ApiResponse } from '@/types/api'

export interface ImportFilePreview {
  headers: string[]
  rows: string[][]
  /** True if the file had more data rows than the preview endpoint will return — see ImportFilePreviewController::MAX_ROWS. */
  truncated: boolean
}

export const importsApi = {
  /** Entity-agnostic — reads back whatever headers/rows the uploaded file actually has, unmapped. See ImportFileMapper. */
  previewFile: async (file: File): Promise<ImportFilePreview> => {
    const formData = new FormData()
    formData.append('file', file)
    const { data } = await httpClient.post<ApiResponse<ImportFilePreview>>('/import-preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },
}
