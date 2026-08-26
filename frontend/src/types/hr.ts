export interface Designation {
  id: number
  name: string
  description: string | null
  is_active: boolean
  created_at: string
}

export interface DesignationPayload {
  name: string
  description?: string | null
  is_active?: boolean
}

export interface LeaveType {
  id: number
  name: string
  days_allowed_per_year: number | null
  is_paid: boolean
  description: string | null
  is_active: boolean
  created_at: string
}

export interface LeaveTypePayload {
  name: string
  days_allowed_per_year?: number | null
  is_paid?: boolean
  description?: string | null
  is_active?: boolean
}

export const LEAVE_STATUSES = ['pending', 'approved', 'rejected', 'cancelled'] as const
export type LeaveStatus = (typeof LEAVE_STATUSES)[number]
export const LEAVE_STATUS_LABELS: Record<LeaveStatus, string> = {
  pending: 'Pending',
  approved: 'Approved',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}

export interface LeaveRequest {
  id: number
  user?: { id: number; full_name: string }
  leave_type?: { id: number; name: string; is_paid: boolean }
  start_date: string
  end_date: string
  days: number
  reason: string
  status: LeaveStatus
  status_label: string
  reviewed_by?: { id: number; full_name: string } | null
  reviewed_at: string | null
  review_notes: string | null
  created_at: string
}

export interface LeaveRequestPayload {
  leave_type_id: number
  start_date: string
  end_date: string
  reason: string
}

export interface SalaryStructure {
  id: number
  user?: { id: number; full_name: string }
  basic_salary: number
  allowances: number
  deductions: number
  net_salary: number
  effective_from: string
  effective_to: string | null
  is_active: boolean
  created_at: string
}

export interface SalaryStructurePayload {
  user_id: number
  basic_salary: number
  allowances?: number
  deductions?: number
  effective_from: string
}

export const PAYSLIP_STATUSES = ['generated', 'paid'] as const
export type PayslipStatusValue = (typeof PAYSLIP_STATUSES)[number]
export const PAYSLIP_STATUS_LABELS: Record<PayslipStatusValue, string> = {
  generated: 'Generated',
  paid: 'Paid',
}

export interface Payslip {
  id: number
  user?: { id: number; full_name: string }
  payslip_number: string
  month: number
  year: number
  basic_salary: number
  allowances: number
  deductions: number
  net_salary: number
  status: PayslipStatusValue
  status_label: string
  paid_at: string | null
  notes: string | null
  created_at: string
}

export interface GeneratePayrollPayload {
  month: number
  year: number
}

export interface GeneratePayrollResult {
  created_count: number
  skipped_count: number
}
