export interface School {
  id: number
  uuid: string
  name: string
  short_name: string
  slug: string
  email: string | null
  phone: string | null
  address_line1: string | null
  address_line2: string | null
  city: string | null
  state: string | null
  postal_code: string | null
  country: string | null
  timezone: string
  locale: string
  is_active: boolean
  created_at: string
}

export interface SchoolPayload {
  name: string
  short_name: string
  email?: string | null
  phone?: string | null
  address_line1?: string | null
  address_line2?: string | null
  city?: string | null
  state?: string | null
  postal_code?: string | null
  country?: string | null
  timezone?: string
  locale?: string
  is_active?: boolean
}
