import { Suspense, type ReactNode } from 'react'
import { QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { BrowserRouter } from 'react-router-dom'
import { Toaster } from 'sonner'
import { Loader2 } from 'lucide-react'
import { queryClient } from './queryClient'
import { AuthProvider } from '@/context/AuthContext'
import { PlatformAuthProvider } from '@/context/PlatformAuthContext'
import { ThemeProvider } from '@/context/ThemeContext'

/**
 * i18next-http-backend (see lib/i18n.ts) fetches the active language's
 * translation file at runtime instead of bundling all 18 languages —
 * react-i18next's useSuspense option means any component calling
 * useTranslation() suspends until that fetch resolves, so this boundary is
 * what actually shows during that (usually sub-100ms, and never again once
 * the browser's HTTP cache has it) gap rather than a blank white page.
 */
function TranslationsLoading() {
  return (
    <div className="flex min-h-svh items-center justify-center bg-background">
      <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" aria-hidden="true" />
      <span className="sr-only">Loading…</span>
    </div>
  )
}

export function AppProviders({ children }: { children: ReactNode }) {
  return (
    <Suspense fallback={<TranslationsLoading />}>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <ThemeProvider>
            <AuthProvider>
              <PlatformAuthProvider>
                {children}
                <Toaster position="top-right" richColors closeButton />
              </PlatformAuthProvider>
            </AuthProvider>
          </ThemeProvider>
        </BrowserRouter>
        {import.meta.env.DEV && <ReactQueryDevtools initialIsOpen={false} />}
      </QueryClientProvider>
    </Suspense>
  )
}
