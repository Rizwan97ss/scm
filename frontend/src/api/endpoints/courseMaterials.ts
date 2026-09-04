import { httpClient } from '@/api/client'
import { createCrudEndpoints } from './crudFactory'
import type { ApiResponse } from '@/types/api'
import type { CourseMaterial, CourseMaterialAttachment, CourseMaterialPayload, CourseMaterialProgress } from '@/types/courseMaterials'

export const courseMaterialsApi = {
  ...createCrudEndpoints<CourseMaterial, CourseMaterialPayload>('course-materials'),
  uploadAttachment: async (materialId: number, file: File): Promise<CourseMaterialAttachment> => {
    const formData = new FormData()
    formData.append('file', file)
    const { data } = await httpClient.post<ApiResponse<CourseMaterialAttachment>>(`/course-materials/${materialId}/attachments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },
  removeAttachment: async (materialId: number, mediaId: number): Promise<void> => {
    await httpClient.delete(`/course-materials/${materialId}/attachments/${mediaId}`)
  },
  markProgress: async (materialId: number, completed?: boolean): Promise<CourseMaterialProgress> => {
    const { data } = await httpClient.post<ApiResponse<CourseMaterialProgress>>(`/course-materials/${materialId}/progress`, { completed })
    return data.data
  },
}
