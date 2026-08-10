import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { usersApi, type CreateUserPayload } from '@/api/endpoints/users'
import { designationsApi } from '@/api/endpoints/hr'
import { queryKeys } from '@/api/queryKeys'
import { Button, Checkbox, FormField, Input, Modal, Select } from '@/components/ui'
import type { User } from '@/types/auth'
import type { Role } from '@/api/endpoints/roles'
import type { ApiError } from '@/api/client'

const emptyForm: CreateUserPayload = {
  first_name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
  roles: [],
  designation_id: null,
  employee_id: '',
  hire_date: '',
}

export function UserFormModal({
  open,
  onOpenChange,
  editing,
  roles,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  editing: User | null
  roles: Role[]
}) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<CreateUserPayload>(emptyForm)
  const [selectedRoles, setSelectedRoles] = useState<string[]>([])

  const { data: designations } = useQuery({ queryKey: queryKeys.designations({ per_page: 100 }), queryFn: () => designationsApi.list({ per_page: 100 }) })

  useEffect(() => {
    if (editing) {
      setForm({
        ...emptyForm,
        first_name: editing.first_name,
        last_name: editing.last_name,
        email: editing.email,
        designation_id: editing.designation?.id ?? null,
        employee_id: editing.employee_id ?? '',
        hire_date: editing.hire_date ?? '',
      })
      setSelectedRoles(editing.roles)
    } else {
      setForm(emptyForm)
      setSelectedRoles([])
    }
  }, [editing, open])

  const invalidate = () => queryClient.invalidateQueries({ queryKey: queryKeys.users() })

  const createMutation = useMutation({
    mutationFn: (payload: CreateUserPayload) => usersApi.create(payload),
    onSuccess: () => {
      toast.success('User created.')
      invalidate()
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const updateMutation = useMutation({
    mutationFn: async () => {
      if (!editing) return
      await usersApi.update(editing.id, {
        first_name: form.first_name,
        last_name: form.last_name,
        email: form.email,
        designation_id: form.designation_id,
        employee_id: form.employee_id || null,
        hire_date: form.hire_date || null,
      })
      await usersApi.updateRoles(editing.id, selectedRoles)
    },
    onSuccess: () => {
      toast.success('User updated.')
      invalidate()
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  function toggleRole(name: string, checked: boolean) {
    setSelectedRoles((current) => (checked ? [...current, name] : current.filter((r) => r !== name)))
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) {
      await updateMutation.mutateAsync()
    } else {
      await createMutation.mutateAsync({ ...form, roles: selectedRoles })
    }
  }

  const isLoading = createMutation.isPending || updateMutation.isPending

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={editing ? 'Edit User' : 'New User'} size="lg">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        <div className="grid grid-cols-2 gap-4">
          <FormField label="First name" htmlFor="first_name" required>
            <Input id="first_name" required value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} />
          </FormField>
          <FormField label="Last name" htmlFor="last_name" required>
            <Input id="last_name" required value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} />
          </FormField>
        </div>
        <FormField label="Email" htmlFor="email" required>
          <Input id="email" type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
        </FormField>

        <div className="grid grid-cols-3 gap-4">
          <FormField label="Designation" htmlFor="designation_id" hint="Optional">
            <Select
              id="designation_id"
              value={form.designation_id ? String(form.designation_id) : 'none'}
              onValueChange={(value) => setForm({ ...form, designation_id: value === 'none' ? null : Number(value) })}
              options={[{ value: 'none', label: 'None' }, ...(designations?.data ?? []).map((d) => ({ value: String(d.id), label: d.name }))]}
            />
          </FormField>
          <FormField label="Employee ID" htmlFor="employee_id" hint="Optional">
            <Input id="employee_id" value={form.employee_id ?? ''} onChange={(e) => setForm({ ...form, employee_id: e.target.value })} />
          </FormField>
          <FormField label="Hire Date" htmlFor="hire_date" hint="Optional">
            <Input id="hire_date" type="date" value={form.hire_date ?? ''} onChange={(e) => setForm({ ...form, hire_date: e.target.value })} />
          </FormField>
        </div>

        {!editing && (
          <div className="grid grid-cols-2 gap-4">
            <FormField label="Password" htmlFor="password" required>
              <Input id="password" type="password" required value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
            </FormField>
            <FormField label="Confirm password" htmlFor="password_confirmation" required>
              <Input
                id="password_confirmation"
                type="password"
                required
                value={form.password_confirmation}
                onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
              />
            </FormField>
          </div>
        )}

        <FormField label="Roles" required>
          <div className="grid grid-cols-2 gap-2 rounded-md border border-border p-3 sm:grid-cols-3">
            {roles.map((role) => (
              <label key={role.id} className="flex items-center gap-2 text-sm">
                <Checkbox checked={selectedRoles.includes(role.name)} onCheckedChange={(checked) => toggleRole(role.name, checked)} />
                {role.name}
              </label>
            ))}
          </div>
        </FormField>

        <Button type="submit" isLoading={isLoading} className="mt-2">
          {editing ? 'Save changes' : 'Create user'}
        </Button>
      </form>
    </Modal>
  )
}
