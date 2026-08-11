import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { env } from '@/config/env'
import type { ApiErrorResponse } from '@/types/api'

/**
 * The API's origin, adjusted for whichever tenant subdomain this page is
 * currently being viewed from. VITE_API_URL only supplies the scheme and
 * port (a build-time default) — the hostname has to follow window.location
 * at request time, or a request made from myschool.localtest.me:5173 would
 * silently go to whatever host was baked in at build time (the wrong
 * tenant's backend, or none, once a real subdomain no longer matches it).
 *
 * A relative VITE_API_URL (e.g. "/api") opts out of this entirely and
 * resolves same-origin — the intended production shape, where a reverse
 * proxy serves the SPA and API from the same host per tenant (see
 * docs/deployment.md) and there is no separate port to preserve.
 */
function resolveApiUrl(): string {
  if (env.apiUrl.startsWith('/')) {
    return env.apiUrl
  }
  const configured = new URL(env.apiUrl)
  const port = configured.port ? `:${configured.port}` : ''
  return `${configured.protocol}//${window.location.hostname}${port}${configured.pathname}`
}

/** Runtime-resolved API base (scheme+host+port+path, no trailing /v1) — see resolveApiUrl() above for why this can't just be env.apiUrl directly. Exported for apiFileUrl.ts, which builds plain <a href> links outside of httpClient and must follow the exact same host resolution. */
export const apiUrl = resolveApiUrl()

/** Origin the API is served from, e.g. "http://myschool.localtest.me:8000". */
const apiOrigin = apiUrl.startsWith('/') ? window.location.origin : new URL(apiUrl).origin

export const httpClient = axios.create({
  baseURL: `${apiUrl}/v1`,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

let csrfCookiePromise: Promise<void> | null = null

/**
 * Sanctum's SPA auth requires a GET to /sanctum/csrf-cookie before the first
 * mutating request in a session; the cookie it sets is then sent automatically
 * (withXSRFToken) on every subsequent request. Cached so repeated calls in the
 * same page load don't re-fetch it.
 */
export function ensureCsrfCookie(): Promise<void> {
  csrfCookiePromise ??= axios
    .get(`${apiOrigin}/sanctum/csrf-cookie`, { withCredentials: true })
    .then(() => undefined)
  return csrfCookiePromise
}

httpClient.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  const method = config.method?.toLowerCase()
  if (method && method !== 'get' && method !== 'head') {
    await ensureCsrfCookie()
  }
  return config
})

/** Normalized shape every thrown API error carries, regardless of what the server returned. */
export interface ApiError {
  message: string
  errors?: Record<string, string[]>
  status?: number
}

httpClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorResponse>) => {
    const apiError: ApiError = {
      message: error.response?.data?.message ?? error.message ?? 'Something went wrong.',
      errors: error.response?.data?.errors,
      status: error.response?.status,
    }
    return Promise.reject(apiError)
  }
)
