export const CERTIFICATE_LAYOUTS = ['classic', 'recognition', 'achievement', 'merit'] as const
export type CertificateLayout = (typeof CERTIFICATE_LAYOUTS)[number]

export interface CertificateSignatory {
  name: string
  title: string
}

export interface CertificateTemplate {
  id: number
  name: string
  type: string
  body: string
  layout: CertificateLayout
  signatories: CertificateSignatory[]
  is_active: boolean
  created_at: string
}

export interface CertificateTemplatePayload {
  name: string
  type: string
  body: string
  layout: CertificateLayout
  signatories: CertificateSignatory[]
  is_active?: boolean
}

export interface Certificate {
  id: number
  student?: { id: number; full_name: string }
  certificate_template?: { id: number; name: string; type: string }
  certificate_number: string
  verification_token: string
  issued_date: string
  issued_by?: { id: number; full_name: string }
  content: string
  created_at: string
}

export interface IssueCertificatePayload {
  student_id: number
  issued_date?: string
}

export interface CertificateVerificationResult {
  valid: boolean
  certificate_number?: string
  student_name?: string
  template_name?: string
  issued_date?: string
  school_name?: string
}
