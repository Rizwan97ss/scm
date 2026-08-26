import { http, HttpResponse } from 'msw'
import { env } from '@/config/env'

const apiOrigin = new URL(env.apiUrl).origin

/**
 * Default handlers every test gets for free (ThemeProvider/AuthProvider both
 * fire these on mount). Individual tests override with `server.use(...)` for
 * the specific behavior they're exercising.
 */
export const handlers = [
  http.get(`${apiOrigin}/sanctum/csrf-cookie`, () => new HttpResponse(null, { status: 204 })),

  http.get(`${env.apiUrl}/v1/settings/public`, () =>
    HttpResponse.json({
      success: true,
      message: null,
      data: {
        'branding.primary_color': '#2563eb',
        'branding.secondary_color': '#0f172a',
        'localization.currency': 'USD',
        'localization.date_format': 'yyyy-MM-dd',
      },
    })
  ),

  http.get(`${env.apiUrl}/v1/auth/me`, () => HttpResponse.json({ success: false, message: 'Unauthenticated.', data: null }, { status: 401 })),
]
