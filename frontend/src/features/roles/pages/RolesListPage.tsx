import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus, ShieldAlert, Trash2 } from 'lucide-react'
import { rolesApi, permissionsApi, type Role } from '@/api/endpoints/roles'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, ConfirmDialog, EmptyState, Skeleton } from '@/components/ui'
import { RoleFormModal } from '../components/RoleFormModal'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import type { ApiError } from '@/api/client'

export function RolesListPage() {
  const { can } = usePermission()
  const queryClient = useQueryClient()
  const { data: roles, isLoading } = useQuery({ queryKey: queryKeys.roles(), queryFn: rolesApi.list })
  const { data: permissionsByModule } = useQuery({ queryKey: queryKeys.permissions, queryFn: permissionsApi.list })

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Role | null>(null)
  const [deleting, setDeleting] = useState<Role | null>(null)

  const removeMutation = useMutation({
    mutationFn: rolesApi.remove,
    onSuccess: () => {
      toast.success('Role deleted.')
      queryClient.invalidateQueries({ queryKey: queryKeys.roles() })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <div>
      <PageHeader
        title="Roles & Permissions"
        description="Create custom roles and control exactly what each one can do."
        actions={
          can('roles.create') && (
            <Button
              onClick={() => {
                setEditing(null)
                setModalOpen(true)
              }}
            >
              <Plus className="h-4 w-4" /> New Role
            </Button>
          )
        }
      />

      {isLoading && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-32 w-full" />
          ))}
        </div>
      )}

      {!isLoading && roles?.length === 0 && <EmptyState title="No roles yet" />}

      {!isLoading && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {roles?.map((role) => (
            <Card key={role.id}>
              <CardHeader className="flex-row items-start justify-between space-y-0">
                <CardTitle className="flex items-center gap-2 text-base">
                  {role.name}
                  {role.is_system && <ShieldAlert className="h-4 w-4 text-muted-foreground" aria-label="System role" />}
                </CardTitle>
                {can('roles.edit') && !role.is_system && (
                  <div className="flex gap-1">
                    <Button variant="outline" size="sm" onClick={() => { setEditing(role); setModalOpen(true) }}>
                      Edit
                    </Button>
                    {can('roles.delete') && (
                      <Button variant="outline" size="icon" onClick={() => setDeleting(role)} aria-label={`Delete ${role.name}`}>
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </div>
                )}
              </CardHeader>
              <CardContent>
                <p className="mb-2 text-xs text-muted-foreground">{role.permissions.length} permission(s)</p>
                <div className="flex flex-wrap gap-1">
                  {role.permissions.slice(0, 6).map((permission) => (
                    <Badge key={permission} variant="default">
                      {permission}
                    </Badge>
                  ))}
                  {role.permissions.length > 6 && <Badge variant="outline">+{role.permissions.length - 6} more</Badge>}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <RoleFormModal open={modalOpen} onOpenChange={setModalOpen} editing={editing} permissionsByModule={permissionsByModule ?? {}} />

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={`Delete role "${deleting?.name}"?`}
        description="Users with only this role will lose all its permissions."
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />
    </div>
  )
}
