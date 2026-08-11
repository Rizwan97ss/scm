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

/**
 * Deliberately not logged in yet — see SignupController's own docblock:
 * this request runs on the central domain, and a session cookie set here
 * can't carry over to the new tenant's own subdomain. checkout_url is
 * where the frontend redirects next; SignupCompletePage (reached from
 * Stripe's success_url, on the tenant's own subdomain) is what actually
 * establishes the session, via the one-time login_token embedded there.
 */
export interface SignupResponse {
  school: { id: number; name: string; slug: string }
  admin: { id: number; first_name: string; last_name: string; email: string }
  checkout_url: string
}

export interface SignupCompletePayload {
  email: string
  token: string
}
