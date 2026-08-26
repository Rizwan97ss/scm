export interface PlatformSchool {
  id: number
  uuid: string
  name: string
  short_name: string
  email: string | null
  is_active: boolean
  stripe_id: string | null
  billing_status: string | null
  trial_ends_at: string | null
  plan: { id: number; key: string; name: string } | null
  usage: {
    students: number
    max_students: number | null
    staff: number
    max_staff: number | null
  }
  created_at: string
}

export interface PlatformMetricSchool {
  id: number
  name: string
  slug: string
  plan_name: string | null
  billing_status: string | null
  students: number
  staff: number
  exams: number
  created_at: string
}

export interface PlatformMetrics {
  total_schools: number
  total_students: number
  total_staff: number
  total_exams: number
  by_billing_status: Record<string, number>
  approximate_mrr_cents: number
  schools_by_month: { month: string; label: string; count: number }[]
  schools: PlatformMetricSchool[]
}

/**
 * Super Admin, on the separate `platform` guard/session — landlord-
 * connection only, no roles/permissions array (being a PlatformUser at all
 * implies full platform access, see backend AppServiceProvider's
 * Gate::before). Not a School's User, and not reachable via useAuth().
 */
export interface PlatformUser {
  id: number
  uuid: string
  first_name: string
  last_name: string
  full_name: string
  email: string
  last_login_at: string | null
  mfa_enabled: boolean
  mfa_grace_period_ends_at: string | null
  created_at: string
}

export interface PlatformLoginPayload {
  email: string
  password: string
  remember?: boolean
}

/** Mirrors the tenant-side LoginResult (types/auth.ts) — see PlatformLoginRequest::authenticate()'s docblock. */
export type PlatformLoginResult = { mfa_required: true; challenge_token: string } | { mfa_required: false; user: PlatformUser }
