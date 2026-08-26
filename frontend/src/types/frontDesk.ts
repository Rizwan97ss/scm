export interface Visitor {
  id: number
  name: string
  phone: string | null
  purpose: string
  whom_to_meet: string | null
  check_in_time: string
  check_out_time: string | null
  notes: string | null
  logged_by?: { id: number; full_name: string }
  created_at: string
}

export interface VisitorPayload {
  name: string
  phone?: string | null
  purpose: string
  whom_to_meet?: string | null
  notes?: string | null
}
