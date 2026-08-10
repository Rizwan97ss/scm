import { httpClient } from '@/api/client'
import { createCrudEndpoints } from './crudFactory'
import type { ApiResponse } from '@/types/api'
import type {
  Homework,
  HomeworkAttachment,
  HomeworkPayload,
  HomeworkRosterRow,
  HomeworkSubmission,
  StudentRemark,
  StudentRemarkPayload,
} from '@/types/homework'

export const homeworkApi = {
  ...createCrudEndpoints<Homework, HomeworkPayload>('homework'),
  uploadAttachment: async (homeworkId: number, file: File): Promise<HomeworkAttachment> => {
    const formData = new FormData()
    formData.append('file', file)
    const { data } = await httpClient.post<ApiResponse<HomeworkAttachment>>(`/homework/${homeworkId}/attachments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },
  removeAttachment: async (homeworkId: number, mediaId: number): Promise<void> => {
    await httpClient.delete(`/homework/${homeworkId}/attachments/${mediaId}`)
  },
  roster: async (homeworkId: number): Promise<HomeworkRosterRow[]> => {
    const { data } = await httpClient.get<ApiResponse<HomeworkRosterRow[]>>(`/homework/${homeworkId}/submissions`)
    return data.data
  },
  submit: async (homeworkId: number, payload: { content?: string | null; file?: File | null }): Promise<HomeworkSubmission> => {
    const formData = new FormData()
    if (payload.content) formData.append('content', payload.content)
    if (payload.file) formData.append('file', payload.file)
    const { data } = await httpClient.post<ApiResponse<HomeworkSubmission>>(`/homework/${homeworkId}/submit`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },
}

export const homeworkSubmissionsApi = {
  grade: async (submissionId: number, payload: { score?: number | null; feedback?: string | null }): Promise<HomeworkSubmission> => {
    const { data } = await httpClient.put<ApiResponse<HomeworkSubmission>>(`/homework-submissions/${submissionId}/grade`, payload)
    return data.data
  },
}

export const studentRemarksApi = createCrudEndpoints<StudentRemark, StudentRemarkPayload>('student-remarks')
