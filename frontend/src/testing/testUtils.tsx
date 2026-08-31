import { Suspense, type ReactElement, type ReactNode } from 'react'
import { render, type RenderOptions } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { AuthProvider } from '@/context/AuthContext'
import { ThemeProvider } from '@/context/ThemeContext'

function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })
}

/** Renders with the same provider stack as the real app, minus router history assumptions. */
export function renderWithProviders(ui: ReactElement, options?: { route?: string } & Omit<RenderOptions, 'wrapper'>) {
  const { route = '/', ...renderOptions } = options ?? {}
  const queryClient = createTestQueryClient()

  function Wrapper({ children }: { children: ReactNode }) {
    return (
      // setupTests.ts preloads 'en' before any test runs, so this never
      // actually suspends in practice -- kept for parity with AppProviders,
      // and as a safety net if a test explicitly switches language.
      <Suspense fallback={null}>
        <QueryClientProvider client={queryClient}>
          <MemoryRouter initialEntries={[route]}>
            <ThemeProvider>
              <AuthProvider>{children}</AuthProvider>
            </ThemeProvider>
          </MemoryRouter>
        </QueryClientProvider>
      </Suspense>
    )
  }

  return render(ui, { wrapper: Wrapper, ...renderOptions })
}

export * from '@testing-library/react'
