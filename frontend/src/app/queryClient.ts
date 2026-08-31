import { QueryCache, QueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { isUnauthenticatedError, type ApiError } from '@/api/client'

function isApiError(error: unknown): error is ApiError {
  return !!error && typeof error === 'object' && 'message' in error
}

/** 401/403/404/422 are permanent for the given request — retrying changes nothing and just delays the real error/redirect reaching the user. */
function shouldRetryQuery(failureCount: number, error: unknown): boolean {
  if (isApiError(error) && error.status && [401, 403, 404, 422].includes(error.status)) return false
  return failureCount < 1
}

/**
 * A 401 mid-session — as opposed to AuthContext's own initial `/auth/me`
 * check, marked `meta: { silentError: true }`, where a 401 just means "not
 * logged in yet" and is expected — means the session has actually expired.
 * `isUnauthenticatedError` was previously defined but never called anywhere
 * in the app: nothing told the user why their action just failed, nothing
 * redirected them, and every subsequent request on the page kept failing
 * the same way. Guarded so a page with several failing queries only
 * triggers this once, not once per query.
 *
 * Hard-navigates (`window.location.href`, not client-side routing) — a
 * full reload guarantees no stale in-memory state from the expired session
 * (an open modal, unsaved form state) survives the forced logout, the same
 * reasoning `AuthContext.logout()` already applies via `queryClient.clear()`.
 * `clear()` alone is enough to drop the cached user too — no separate
 * `setQueryData(queryKeys.me, null)` needed, since `clear()` removes every
 * cached query including that one.
 */
let sessionExpiryHandled = false

function handleSessionExpiry() {
  if (sessionExpiryHandled) return
  sessionExpiryHandled = true
  toast.error('Your session has expired — please log in again.')
  queryClient.clear()
  window.location.href = '/login'
}

/** Exported for direct unit testing — QueryCache's real `onError` isn't itself re-invokable once passed to the QueryClient constructor. */
export function handleApiError(error: unknown, meta?: Readonly<Record<string, unknown>>) {
  if (!isApiError(error)) return

  if (isUnauthenticatedError(error) && !meta?.silentError && !meta?.silent401) {
    handleSessionExpiry()
    return
  }

  if (meta?.silentError) return
  toast.error(error.message)
}

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: shouldRetryQuery,
      refetchOnWindowFocus: false,
    },
    mutations: {
      onError: (error) => handleApiError(error),
    },
  },
  queryCache: new QueryCache({
    onError: (error, query) => handleApiError(error, query.meta),
  }),
})
