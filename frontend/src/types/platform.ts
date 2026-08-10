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

export interface PlatformMetrics {
  total_schools: number
  by_billing_status: Record<string, number>
  approximate_mrr_cents: number
}
