import { Navigate, Outlet } from 'react-router-dom'
import { usePlatformAuth } from '@/context/PlatformAuthContext'
import { needsMfaSetup } from '@/utils/mfaEnforcement'
import { routePaths } from './routePaths'

/** Landlord-side twin of RequireMfaSetup — same logic, 'platform' guard. */
export function RequirePlatformMfaSetup() {
  const { platformUser } = usePlatformAuth()

  if (needsMfaSetup(platformUser)) {
    return <Navigate to={routePaths.platformMfaSetup} replace />
  }

  return <Outlet />
}
