import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { env } from '@/config/env'
import type { ApiErrorResponse } from '@/types/api'

/** Origin the API is served from, e.g. "http://localhost:8000" (VITE_API_URL minus /api). */
const apiOrigin = new URL(env.apiUrl).origin

export const httpClient = axios.create({
  baseURL: `${env.apiUrl}/v1`,
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
