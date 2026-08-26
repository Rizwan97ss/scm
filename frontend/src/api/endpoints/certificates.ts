import { httpClient } from '@/api/client'
import { createCrudEndpoints } from './crudFactory'
import { apiFileUrl } from '@/utils/apiFileUrl'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { Certificate, CertificateTemplate, CertificateTemplatePayload, IssueCertificatePayload } from '@/types/certificates'

export const certificateTemplatesApi = {
  ...createCrudEndpoints<CertificateTemplate, CertificateTemplatePayload>('certificate-templates'),
  issue: async (templateId: number, payload: IssueCertificatePayload): Promise<Certificate> => {
    const { data } = await httpClient.post<ApiResponse<Certificate>>(`/certificate-templates/${templateId}/issue`, payload)
    return data.data
  },
}

export const certificatesApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<Certificate>> => {
    const { data } = await httpClient.get<PaginatedResponse<Certificate>>('/certificates', { params })
    return data
  },
  pdfUrl: (id: number) => apiFileUrl(`/certificates/${id}/pdf`),
}

export const idCardsApi = {
  studentPdfUrl: (studentId: number) => apiFileUrl(`/students/${studentId}/id-card/pdf`),
  staffPdfUrl: (userId: number) => apiFileUrl(`/users/${userId}/id-card/pdf`),
}
