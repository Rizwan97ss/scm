import type { Gender, UserStatus } from './enums'

export interface UserSchoolSnapshot {
  id: number
  name: string
  plan_name: string | null
  billing_status: string | null
  trial_ends_at: string | null
}

export interface User {
  id: number
  uuid: string
  first_name: string
  last_name: string
  full_name: string
  email: string
  username: string | null
  phone: string | null
  gender: Gender | null
  date_of_birth: string | null
  status: UserStatus
  must_change_password: boolean
  last_login_at: string | null
  mfa_enabled: boolean
  mfa_grace_period_ends_at: string | null
  avatar_url: string | null
  designation?: { id: number; name: string } | null
  employee_id: string | null
  hire_date: string | null
  roles: string[]
  permissions?: string[]
  // Null for Super Admin and anywhere the backend didn't eager-load
  // `school` on the resource (see UserResource's docblock).
  school?: UserSchoolSnapshot | null
  created_at: string
}

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

/** Mirrors the backend's LoginRequest::authenticate() docblock — mfa_required true means no session exists yet. */
export type LoginResult = { mfa_required: true; challenge_token: string } | { mfa_required: false; user: User }

export interface MfaChallengePayload {
  challenge_token: string
  code: string
}

export interface MfaSetupResponse {
  secret: string
  qr_code: string
}

export interface MfaRecoveryCodesResponse {
  recovery_codes: string[]
}

export interface UpdatePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}
