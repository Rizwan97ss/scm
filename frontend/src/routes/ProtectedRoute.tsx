import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import { LoadingScreen } from '@/components/feedback/LoadingScreen'
import { routePaths } from './routePaths'

export function ProtectedRoute() {
  const { isAuthenticated, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) return <LoadingScreen />

  if (!isAuthenticated) {
    return <Navigate to={routePaths.login} state={{ from: location }} replace />
  }

  return <Outlet />
}
