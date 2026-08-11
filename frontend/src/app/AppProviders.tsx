import type { ReactNode } from 'react'
import { QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { BrowserRouter } from 'react-router-dom'
import { Toaster } from 'sonner'
import { queryClient } from './queryClient'
import { AuthProvider } from '@/context/AuthContext'
import { PlatformAuthProvider } from '@/context/PlatformAuthContext'
import { ThemeProvider } from '@/context/ThemeContext'

export function AppProviders({ children }: { children: ReactNode }) {
  return (
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
  )
}
