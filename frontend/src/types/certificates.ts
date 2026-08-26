export interface CertificateTemplate {
  id: number
  name: string
  type: string
  body: string
  is_active: boolean
  created_at: string
}

export interface CertificateTemplatePayload {
  name: string
  type: string
  body: string
  is_active?: boolean
}

export interface Certificate {
  id: number
  student?: { id: number; full_name: string }
  certificate_template?: { id: number; name: string; type: string }
  certificate_number: string
  issued_date: string
  issued_by?: { id: number; full_name: string }
  content: string
  created_at: string
}

export interface IssueCertificatePayload {
  student_id: number
  issued_date?: string
}
