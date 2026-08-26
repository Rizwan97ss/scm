import { Outlet } from 'react-router-dom'
import { usePermission } from '@/hooks/usePermission'
import { Forbidden } from '@/components/feedback/Forbidden'

/** Nest under ProtectedRoute. Renders Forbidden unless the user holds at least one of `permissions`. */
export function PermissionRoute({ permissions }: { permissions: string[] }) {
  const { can } = usePermission()

  if (!can(...permissions)) {
    return <Forbidden />
  }

  return <Outlet />
}
