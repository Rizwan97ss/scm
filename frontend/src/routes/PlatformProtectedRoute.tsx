import { Navigate, Outlet } from 'react-router-dom'
import { usePlatformAuth } from '@/context/PlatformAuthContext'
import { LoadingScreen } from '@/components/feedback/LoadingScreen'
import { routePaths } from './routePaths'

export function PlatformProtectedRoute() {
  const { isAuthenticated, isLoading } = usePlatformAuth()

  if (isLoading) return <LoadingScreen />

  if (!isAuthenticated) {
    return <Navigate to={routePaths.platformLogin} replace />
  }

  return <Outlet />
}
