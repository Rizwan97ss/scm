import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import { needsMfaSetup } from '@/utils/mfaEnforcement'
import { routePaths } from './routePaths'

/**
 * Sits between ProtectedRoute and AppShell — mirrors the backend's
 * EnsureMfaEnrolled: once a user's grace period is over and they still
 * haven't confirmed MFA, every route past this point hard-redirects to
 * MfaSetupPage instead of rendering. MfaSetupPage itself is registered as a
 * sibling OUTSIDE this wrapper (still inside ProtectedRoute) so it stays
 * reachable — see AppRouter.
 */
export function RequireMfaSetup() {
  const { user } = useAuth()

  if (needsMfaSetup(user)) {
    return <Navigate to={routePaths.mfaSetup} replace />
  }

  return <Outlet />
}
