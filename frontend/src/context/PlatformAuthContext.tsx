import { createContext, useCallback, useContext, useMemo, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchPlatformMe,
  platformLogin as loginRequest,
  platformLogout as logoutRequest,
  verifyPlatformMfaChallenge as verifyMfaChallengeRequest,
} from '@/api/endpoints/platformAuth'
import { queryKeys } from '@/api/queryKeys'
import type { MfaChallengePayload } from '@/types/auth'
import type { PlatformLoginPayload, PlatformLoginResult, PlatformUser } from '@/types/platform'

interface PlatformAuthContextValue {
  platformUser: PlatformUser | null
  isLoading: boolean
  isAuthenticated: boolean
  login: (payload: PlatformLoginPayload) => Promise<PlatformLoginResult>
  verifyMfaChallenge: (payload: MfaChallengePayload) => Promise<PlatformUser>
  logout: () => Promise<void>
}

const PlatformAuthContext = createContext<PlatformAuthContextValue | null>(null)

/**
 * Entirely separate from AuthProvider/AuthContext — the `platform` guard is
 * its own session, independent of whatever tenant session (if any) shares
 * this browser. Mounted alongside AuthProvider at the app root (harmless
 * when unused: the /platform-me probe below silently no-ops on a 401,
 * exactly like useAuth's own /auth/me probe).
 */
export function PlatformAuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()

  const {
    data: platformUser,
    isLoading,
    isPending,
  } = useQuery({
    queryKey: queryKeys.platformMe,
    queryFn: fetchPlatformMe,
    retry: false,
    staleTime: 60 * 1000,
    refetchOnWindowFocus: false,
    throwOnError: false,
    meta: { silent401: true },
  })

  const login = useCallback(
    async (payload: PlatformLoginPayload) => {
      const result = await loginRequest(payload)
      if (!result.mfa_required) {
        queryClient.setQueryData(queryKeys.platformMe, result.user)
      }
      return result
    },
    [queryClient]
  )

  const verifyMfaChallenge = useCallback(
    async (payload: MfaChallengePayload) => {
      const loggedInUser = await verifyMfaChallengeRequest(payload)
      queryClient.setQueryData(queryKeys.platformMe, loggedInUser)
      return loggedInUser
    },
    [queryClient]
  )

  const logout = useCallback(async () => {
    await logoutRequest()
    queryClient.setQueryData(queryKeys.platformMe, null)
    queryClient.clear()
  }, [queryClient])

  const value = useMemo<PlatformAuthContextValue>(
    () => ({
      platformUser: platformUser ?? null,
      isLoading: isLoading || isPending,
      isAuthenticated: !!platformUser,
      login,
      verifyMfaChallenge,
      logout,
    }),
    [platformUser, isLoading, isPending, login, verifyMfaChallenge, logout]
  )

  return <PlatformAuthContext.Provider value={value}>{children}</PlatformAuthContext.Provider>
}

export function usePlatformAuth(): PlatformAuthContextValue {
  const ctx = useContext(PlatformAuthContext)
  if (!ctx) throw new Error('usePlatformAuth must be used within a PlatformAuthProvider')
  return ctx
}
