import type { User } from './auth'

export interface SignupPayload {
  school: {
    name: string
    short_name: string
    email?: string
    phone?: string
    timezone?: string
    locale?: string
  }
  admin: {
    first_name: string
    last_name: string
    email: string
    password: string
    password_confirmation: string
  }
  plan_id: number
}

export interface SignupResponse {
  user: User
  checkout_url: string
}
