import fs from 'node:fs'
import path from 'node:path'
import { http, HttpResponse } from 'msw'
import { apiUrl } from '@/api/client'

/**
 * apiUrl (not config/env's raw apiUrl) -- client.ts's resolveApiUrl()
 * rewrites the configured host to follow window.location.hostname (tenant
 * subdomain following, see its own docblock), which in jsdom resolves to
 * "localhost" regardless of what .env's VITE_API_URL says. Matching that
 * exact runtime value, rather than re-deriving from env.apiUrl here, is
 * what keeps these handlers actually intercepting the requests httpClient
 * sends instead of silently missing them.
 */
const apiOrigin = apiUrl.startsWith('/') ? window.location.origin : new URL(apiUrl).origin

/**
 * i18next-http-backend fetches translations at runtime (see lib/i18n.ts) —
 * without this, useTranslation() suspends forever in tests since there's no
 * real server to answer it. Serves the actual JSON so tests see real
 * strings, not a mock; falls back to 'en' for a language whose file doesn't
 * exist rather than 404ing (LanguageDetector's exact locale isn't worth
 * pinning down in jsdom, and every test wants deterministic English anyway).
 */
function loadLocaleFile(lng: string): Record<string, unknown> {
  const localesDir = path.resolve(__dirname, '../../public/locales')
  const requested = path.join(localesDir, lng, 'translation.json')
  const file = fs.existsSync(requested) ? requested : path.join(localesDir, 'en', 'translation.json')
  return JSON.parse(fs.readFileSync(file, 'utf-8'))
}

/**
 * Default handlers every test gets for free (ThemeProvider/AuthProvider both
 * fire these on mount). Individual tests override with `server.use(...)` for
 * the specific behavior they're exercising.
 */
export const handlers = [
  http.get(`${apiOrigin}/sanctum/csrf-cookie`, () => new HttpResponse(null, { status: 204 })),

  http.get('/locales/:lng/translation.json', ({ params }) => HttpResponse.json(loadLocaleFile(params.lng as string))),

  http.get(`${apiUrl}/v1/settings/public`, () =>
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

  http.get(`${apiUrl}/v1/auth/me`, () => HttpResponse.json({ success: false, message: 'Unauthenticated.', data: null }, { status: 401 })),
]
