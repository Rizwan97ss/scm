import { createContext, useCallback, useContext, useMemo, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchMe, login as loginRequest, logout as logoutRequest } from '@/api/endpoints/auth'
import { queryKeys } from '@/api/queryKeys'
import { disconnectEcho } from '@/lib/echo'
import type { LoginPayload, User } from '@/types/auth'
import type { ApiError } from '@/api/client'

interface AuthContextValue {
  user: User | null
  isLoading: boolean
  isAuthenticated: boolean
  login: (payload: LoginPayload) => Promise<User>
  logout: () => Promise<void>
  hasRole: (...roles: string[]) => boolean
  hasPermission: (...permissions: string[]) => boolean
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()

  const {
    data: user,
    isLoading,
    isPending,
  } = useQuery({
    queryKey: queryKeys.me,
    queryFn: fetchMe,
    retry: false,
    staleTime: 60 * 1000,
    refetchOnWindowFocus: false,
    throwOnError: false,
    // A 401 here just means "not logged in" — not an error state the UI
    // should surface. Since the tenancy conversion, a 404 means the same
    // thing for a different reason: /auth/me is tenant-zone-only, and this
    // provider is mounted globally, so it fires on central-domain pages
    // (login, signup, the platform console) with no tenant to resolve at
    // all — also not something the UI should treat as a real error.
    select: (data) => data,
    meta: { silentError: true },
  })

  const login = useCallback(
    async (payload: LoginPayload) => {
      const loggedInUser = await loginRequest(payload)
      queryClient.setQueryData(queryKeys.me, loggedInUser)
      return loggedInUser
    },
    [queryClient]
  )

  const logout = useCallback(async () => {
    await logoutRequest()
    disconnectEcho()
    queryClient.setQueryData(queryKeys.me, null)
    queryClient.clear()
  }, [queryClient])

  const hasRole = useCallback((...roles: string[]) => !!user && roles.some((role) => user.roles.includes(role)), [user])

  const hasPermission = useCallback(
    (...permissions: string[]) => !!user?.permissions && permissions.some((permission) => user.permissions!.includes(permission)),
    [user]
  )

  const value = useMemo<AuthContextValue>(
    () => ({
      user: user ?? null,
      isLoading: isLoading || isPending,
      isAuthenticated: !!user,
      login,
      logout,
      hasRole,
      hasPermission,
    }),
    [user, isLoading, isPending, login, logout, hasRole, hasPermission]
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within an AuthProvider')
  return ctx
}

export function isUnauthenticatedError(error: unknown): error is ApiError {
  return !!error && typeof error === 'object' && 'status' in error && (error as ApiError).status === 401
}
