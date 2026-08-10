export interface Plan {
  id: number
  key: string
  name: string
  description: string | null
  price_cents: number
  currency: string
  trial_days: number
  max_students: number | null
  max_staff: number | null
  feature_flags: Record<string, boolean> | null
}
