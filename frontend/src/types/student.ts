import type { EnrollmentAction, Gender, GuardianRelationship, StudentStatus } from './enums'

export interface StudentDocument {
  id: number
  collection: string
  file_name: string
  size: number
  url: string
  created_at: string
}

export interface StudentGuardianSummary {
  id: number
  full_name: string
  phone: string | null
  email: string | null
  relationship_type: GuardianRelationship
  is_primary: boolean
  can_pickup: boolean
}

export interface Student {
  id: number
  uuid: string
  admission_number: string
  first_name: string
  last_name: string
  full_name: string
  gender: Gender
  date_of_birth: string
  blood_group: string | null
  nationality: string | null
  academic_year_id: number
  current_grade_level_id: number | null
  grade_level?: { id: number; name: string } | null
  current_section_id: number | null
  section?: { id: number; name: string } | null
  roll_number: string | null
  admission_date: string
  status: StudentStatus
  previous_school_name: string | null
  previous_school_details: string | null
  medical_info: string | null
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  address_line1: string | null
  address_line2: string | null
  city: string | null
  state: string | null
  postal_code: string | null
  country: string | null
  photo_url: string | null
  guardians?: StudentGuardianSummary[]
  documents?: StudentDocument[]
  created_at: string
}

export interface StudentGuardianInput {
  guardian_id?: number | null
  first_name?: string
  last_name?: string
  email?: string | null
  phone?: string
  relationship_type: GuardianRelationship
  is_primary?: boolean
  can_pickup?: boolean
}

export interface StudentPayload {
  first_name: string
  last_name: string
  gender: Gender
  date_of_birth: string
  blood_group?: string | null
  nationality?: string | null
  academic_year_id: number
  current_grade_level_id?: number | null
  current_section_id?: number | null
  roll_number?: string | null
  admission_date: string
  previous_school_name?: string | null
  previous_school_details?: string | null
  medical_info?: string | null
  emergency_contact_name?: string | null
  emergency_contact_phone?: string | null
  address_line1?: string | null
  address_line2?: string | null
  city?: string | null
  state?: string | null
  postal_code?: string | null
  country?: string | null
  guardians?: StudentGuardianInput[]
}

export interface EnrollmentActionPayload {
  reason?: string | null
  effective_date?: string | null
}

export interface PromoteStudentPayload extends EnrollmentActionPayload {
  to_grade_level_id: number
  to_section_id: number
  to_academic_year_id: number
}

export interface ReactivateStudentPayload extends EnrollmentActionPayload {
  to_grade_level_id: number
  to_section_id: number
}

export interface StudentEnrollmentHistoryEntry {
  id: number
  action: EnrollmentAction
  from_grade_level: string | null
  to_grade_level: string | null
  from_section: string | null
  to_section: string | null
  reason: string | null
  effective_date: string
  performed_by: string | null
  created_at: string
}

export interface Guardian {
  id: number
  uuid: string
  first_name: string
  last_name: string
  full_name: string
  email: string | null
  phone: string
  occupation: string | null
  national_id: string | null
  address_line1: string | null
  address_line2: string | null
  city: string | null
  state: string | null
  postal_code: string | null
  country: string | null
  has_portal_access: boolean
  invited_at: string | null
  students?: {
    id: number
    full_name: string
    admission_number: string
    relationship_type: GuardianRelationship
    is_primary: boolean
  }[]
}

export interface StudentImportResult {
  imported_count: number
  failed_count: number
  failures: { row: number; attribute: string; errors: string[] }[]
}
