import { useAuth } from '@/context/AuthContext'

/**
 * Convenience wrapper around AuthContext's role/permission checks for use in
 * render logic (`const { can } = usePermission(); if (can('students.edit')) ...`).
 */
export function usePermission() {
  const { hasPermission, hasRole } = useAuth()
  return { can: hasPermission, hasRole }
}
