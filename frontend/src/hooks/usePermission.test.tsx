import { describe, expect, it } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { usePermission } from './usePermission'
import { AuthProvider } from '@/context/AuthContext'

function wrapper({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return (
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <AuthProvider>{children}</AuthProvider>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

describe('usePermission', () => {
  it('defaults to denying every permission and role while unauthenticated', async () => {
    const { result } = renderHook(() => usePermission(), { wrapper })

    await waitFor(() => expect(result.current.can('students.view')).toBe(false))
    expect(result.current.hasRole('School Admin')).toBe(false)
    expect(result.current.can()).toBe(false)
  })
})
