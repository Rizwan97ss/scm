import { beforeEach, describe, expect, it, vi } from 'vitest'
import { toast } from 'sonner'

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn(), message: vi.fn(), warning: vi.fn() } }))

/**
 * `queryClient.ts` tracks "have we already redirected for an expired
 * session" as module-level state (see its own docblock — intentional in a
 * real browser, since a redirect unloads the page and nothing runs after
 * it) — `resetModules` + a fresh dynamic import gives each test a clean
 * instance instead of that state leaking between test cases the way it
 * never would across a real page load.
 */
async function freshQueryClientModule() {
  vi.resetModules()
  return import('./queryClient')
}

describe('queryClient error handling', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    Object.defineProperty(window, 'location', {
      value: { href: '' },
      writable: true,
      configurable: true,
    })
  })

  it('a 401 from a normal query shows a session-expired toast and redirects to /login', async () => {
    const { handleApiError } = await freshQueryClientModule()

    handleApiError({ message: 'Unauthenticated.', status: 401 }, undefined)

    expect(toast.error).toHaveBeenCalledWith('Your session has expired — please log in again.')
    expect(window.location.href).toBe('/login')
  })

  it('does not redirect for the silent /auth/me check (meta.silentError)', async () => {
    const { handleApiError } = await freshQueryClientModule()

    handleApiError({ message: 'Unauthenticated.', status: 401 }, { silentError: true })

    expect(toast.error).not.toHaveBeenCalled()
    expect(window.location.href).toBe('')
  })

  it('does not toast or redirect for a silent 401 probe (meta.silent401)', async () => {
    const { handleApiError } = await freshQueryClientModule()

    handleApiError({ message: 'Unauthenticated.', status: 401 }, { silent401: true })

    expect(toast.error).not.toHaveBeenCalled()
    expect(window.location.href).toBe('')
  })

  it('still shows a plain toast for a non-401 error from a silent401 query', async () => {
    const { handleApiError } = await freshQueryClientModule()

    handleApiError({ message: 'Something went wrong.', status: 500 }, { silent401: true })

    expect(toast.error).toHaveBeenCalledWith('Something went wrong.')
    expect(window.location.href).toBe('')
  })

  it('does not redirect twice for two 401s on the same page (module-level guard)', async () => {
    const { handleApiError } = await freshQueryClientModule()

    handleApiError({ message: 'Unauthenticated.', status: 401 }, undefined)
    window.location.href = '' // simulate the redirect not actually navigating away, as it wouldn't mid-test
    handleApiError({ message: 'Unauthenticated.', status: 401 }, undefined)

    expect(toast.error).toHaveBeenCalledTimes(1)
    expect(window.location.href).toBe('')
  })

  it('a non-401 error still shows a plain toast, not a session-expiry redirect', async () => {
    const { handleApiError } = await freshQueryClientModule()

    handleApiError({ message: 'Something went wrong.', status: 500 }, undefined)

    expect(toast.error).toHaveBeenCalledWith('Something went wrong.')
    expect(window.location.href).toBe('')
  })

  it('clears the whole query cache (including the cached user) on session expiry', async () => {
    const { queryClient, handleApiError } = await freshQueryClientModule()
    const { queryKeys } = await import('@/api/queryKeys')
    queryClient.setQueryData(queryKeys.me, { id: 1, full_name: 'Test User' })

    handleApiError({ message: 'Unauthenticated.', status: 401 }, undefined)

    expect(queryClient.getQueryData(queryKeys.me)).toBeUndefined()
    expect(queryClient.getQueryCache().getAll()).toHaveLength(0)
  })
})
