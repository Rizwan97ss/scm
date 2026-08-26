import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { rolesApi, type PermissionsByModule, type Role, type RolePayload } from '@/api/endpoints/roles'
import { queryKeys } from '@/api/queryKeys'
import { Button, Checkbox, FormField, Input, Modal } from '@/components/ui'
import type { ApiError } from '@/api/client'

export function RoleFormModal({
  open,
  onOpenChange,
  editing,
  permissionsByModule,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  editing: Role | null
  permissionsByModule: PermissionsByModule
}) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [name, setName] = useState('')
  const [selected, setSelected] = useState<Set<string>>(new Set())

  useEffect(() => {
    setName(editing?.name ?? '')
    setSelected(new Set(editing?.permissions ?? []))
  }, [editing, open])

  const mutation = useMutation({
    mutationFn: async (payload: RolePayload) => (editing ? rolesApi.update(editing.id, payload) : rolesApi.create(payload)),
    onSuccess: () => {
      toast.success(editing ? t('roles.roleUpdatedToast') : t('roles.roleCreatedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.roles() })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  function toggle(permission: string, checked: boolean) {
    setSelected((current) => {
      const next = new Set(current)
      if (checked) next.add(permission)
      else next.delete(permission)
      return next
    })
  }

  function toggleModule(modulePermissions: { name: string }[], checked: boolean) {
    setSelected((current) => {
      const next = new Set(current)
      for (const permission of modulePermissions) {
        if (checked) next.add(permission.name)
        else next.delete(permission.name)
      }
      return next
    })
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    await mutation.mutateAsync({ name, permissions: Array.from(selected) })
  }

  return (
    <Modal
      open={open}
      onOpenChange={onOpenChange}
      title={editing ? t('common.editItem', { item: t('entities.role') }) : t('common.newItem', { item: t('entities.role') })}
      size="lg"
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        <FormField label={t('roles.roleNameLabel')} htmlFor="name" required>
          <Input id="name" required value={name} onChange={(e) => setName(e.target.value)} />
        </FormField>

        <FormField label={t('roles.permissionsLabel')}>
          <div className="max-h-96 overflow-y-auto rounded-md border border-border">
            {Object.entries(permissionsByModule).map(([moduleName, perms]) => {
              const allChecked = perms.every((p) => selected.has(p.name))
              return (
                <div key={moduleName} className="border-b border-border p-3 last:border-0">
                  <label className="mb-2 flex items-center gap-2 text-sm font-medium capitalize">
                    <Checkbox checked={allChecked} onCheckedChange={(checked) => toggleModule(perms, checked)} />
                    {moduleName.replace(/-/g, ' ')}
                  </label>
                  <div className="ml-6 grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                    {perms.map((permission) => (
                      <label key={permission.name} className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Checkbox checked={selected.has(permission.name)} onCheckedChange={(checked) => toggle(permission.name, checked)} />
                        {permission.action.replace(/-/g, ' ')}
                      </label>
                    ))}
                  </div>
                </div>
              )
            })}
          </div>
        </FormField>

        <Button type="submit" isLoading={mutation.isPending} className="mt-2">
          {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.role') })}
        </Button>
      </form>
    </Modal>
  )
}
