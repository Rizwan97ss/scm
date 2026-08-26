import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
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
  const { t } = useTranslation()
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
      toast.success(t('roles.roleDeletedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.roles() })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <div>
      <PageHeader
        title={t('nav.roles_permissions')}
        description={t('roles.description')}
        actions={
          can('roles.create') && (
            <Button
              onClick={() => {
                setEditing(null)
                setModalOpen(true)
              }}
            >
              <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.role') })}
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

      {!isLoading && roles?.length === 0 && <EmptyState title={t('roles.noRolesYet')} />}

      {!isLoading && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {roles?.map((role) => (
            <Card key={role.id}>
              <CardHeader className="flex-row items-start justify-between space-y-0">
                <CardTitle className="flex items-center gap-2 text-base">
                  {role.name}
                  {role.is_system && <ShieldAlert className="h-4 w-4 text-muted-foreground" aria-label={t('roles.systemRoleAria')} />}
                </CardTitle>
                {can('roles.edit') && !role.is_system && (
                  <div className="flex gap-1">
                    <Button variant="outline" size="sm" onClick={() => { setEditing(role); setModalOpen(true) }}>
                      {t('common.edit')}
                    </Button>
                    {can('roles.delete') && (
                      <Button variant="outline" size="icon" onClick={() => setDeleting(role)} aria-label={t('common.deleteItem', { item: role.name })}>
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </div>
                )}
              </CardHeader>
              <CardContent>
                <p className="mb-2 text-xs text-muted-foreground">{t('roles.permissionsCountLabel', { count: role.permissions.length })}</p>
                <div className="flex flex-wrap gap-1">
                  {role.permissions.slice(0, 6).map((permission) => (
                    <Badge key={permission} variant="default">
                      {permission}
                    </Badge>
                  ))}
                  {role.permissions.length > 6 && <Badge variant="outline">{t('roles.moreCount', { count: role.permissions.length - 6 })}</Badge>}
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
        title={t('roles.deleteRoleConfirmTitle', { name: deleting?.name })}
        description={t('roles.deleteRoleConfirmDescription')}
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />
    </div>
  )
}
