import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type {
  PromoteStudentPayload,
  ReactivateStudentPayload,
  EnrollmentActionPayload,
  Student,
  StudentDocument,
  StudentEnrollmentHistoryEntry,
  StudentGuardianInput,
  StudentPayload,
} from '@/types/student'
import type { ImportResult } from '@/types/import'

export const studentsApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<Student>> => {
    const { data } = await httpClient.get<PaginatedResponse<Student>>('/students', { params })
    return data
  },
  get: async (id: number): Promise<Student> => {
    const { data } = await httpClient.get<ApiResponse<Student>>(`/students/${id}`)
    return data.data
  },
  create: async (payload: StudentPayload): Promise<Student> => {
    const { data } = await httpClient.post<ApiResponse<Student>>('/students', payload)
    return data.data
  },
  update: async (id: number, payload: Partial<StudentPayload>): Promise<Student> => {
    const { data } = await httpClient.put<ApiResponse<Student>>(`/students/${id}`, payload)
    return data.data
  },
  remove: async (id: number): Promise<void> => {
    await httpClient.delete(`/students/${id}`)
  },

  documents: async (id: number): Promise<StudentDocument[]> => {
    const { data } = await httpClient.get<ApiResponse<StudentDocument[]>>(`/students/${id}/documents`)
    return data.data
  },
  uploadDocument: async (id: number, collection: string, file: File): Promise<StudentDocument> => {
    const formData = new FormData()
    formData.append('collection', collection)
    formData.append('file', file)
    const { data } = await httpClient.post<ApiResponse<StudentDocument>>(`/students/${id}/documents`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },
  removeDocument: async (id: number, mediaId: number): Promise<void> => {
    await httpClient.delete(`/students/${id}/documents/${mediaId}`)
  },

  attachGuardian: async (id: number, payload: StudentGuardianInput): Promise<Student> => {
    const { data } = await httpClient.post<ApiResponse<Student>>(`/students/${id}/guardians`, payload)
    return data.data
  },
  updateGuardianLink: async (
    id: number,
    guardianId: number,
    payload: Partial<Pick<StudentGuardianInput, 'relationship_type' | 'is_primary' | 'can_pickup'>>
  ): Promise<Student> => {
    const { data } = await httpClient.put<ApiResponse<Student>>(`/students/${id}/guardians/${guardianId}`, payload)
    return data.data
  },
  detachGuardian: async (id: number, guardianId: number): Promise<void> => {
    await httpClient.delete(`/students/${id}/guardians/${guardianId}`)
  },

  enrollmentHistory: async (id: number): Promise<StudentEnrollmentHistoryEntry[]> => {
    const { data } = await httpClient.get<ApiResponse<StudentEnrollmentHistoryEntry[]>>(`/students/${id}/enrollment-history`)
    return data.data
  },
  promote: async (id: number, payload: PromoteStudentPayload): Promise<Student> => {
    const { data } = await httpClient.post<ApiResponse<Student>>(`/students/${id}/promote`, payload)
    return data.data
  },
  transfer: async (id: number, payload: EnrollmentActionPayload): Promise<Student> => {
    const { data } = await httpClient.post<ApiResponse<Student>>(`/students/${id}/transfer`, payload)
    return data.data
  },
  withdraw: async (id: number, payload: EnrollmentActionPayload): Promise<Student> => {
    const { data } = await httpClient.post<ApiResponse<Student>>(`/students/${id}/withdraw`, payload)
    return data.data
  },
  graduate: async (id: number, payload: EnrollmentActionPayload): Promise<Student> => {
    const { data } = await httpClient.post<ApiResponse<Student>>(`/students/${id}/graduate`, payload)
    return data.data
  },
  reactivate: async (id: number, payload: ReactivateStudentPayload): Promise<Student> => {
    const { data } = await httpClient.post<ApiResponse<Student>>(`/students/${id}/reactivate`, payload)
    return data.data
  },
  bulkPromote: async (payload: PromoteStudentPayload & { student_ids: number[] }): Promise<{ promoted_count: number }> => {
    const { data } = await httpClient.post<ApiResponse<{ promoted_count: number }>>('/students/bulk/promote', payload)
    return data.data
  },
  invitePortalUser: async (id: number): Promise<{ username: string; temporary_password: string }> => {
    const { data } = await httpClient.post<ApiResponse<{ username: string; temporary_password: string }>>(
      `/students/${id}/invite-portal-user`
    )
    return data.data
  },

  importTemplateUrl: '/students/import/template',
  import: async (file: File, dryRun = false): Promise<ImportResult> => {
    const formData = new FormData()
    formData.append('file', file)
    if (dryRun) formData.append('dry_run', '1')
    const { data } = await httpClient.post<ApiResponse<ImportResult>>('/students/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },
  exportUrl: '/students/export',
}
