import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Plus, Trash2, Send } from 'lucide-react'
import { studentsApi } from '@/api/endpoints/students'
import { guardiansApi } from '@/api/endpoints/guardians'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { Badge, Button, Checkbox, EmptyState, FormField, Input, Modal, Select } from '@/components/ui'
import { GUARDIAN_RELATIONSHIPS, GUARDIAN_RELATIONSHIP_LABEL_KEYS } from '@/types/enums'
import type { Student, StudentGuardianInput } from '@/types/student'
import type { ApiError } from '@/api/client'

const emptyForm: StudentGuardianInput = { first_name: '', last_name: '', phone: '', relationship_type: 'guardian', is_primary: false, can_pickup: true }

export function StudentGuardianList({ student }: { student: Student }) {
  const { t } = useTranslation()
  const { can } = usePermission()
  const queryClient = useQueryClient()
  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<StudentGuardianInput>(emptyForm)

  const invalidate = () => queryClient.invalidateQueries({ queryKey: queryKeys.student(student.id) })

  const attachMutation = useMutation({
    mutationFn: (payload: StudentGuardianInput) => studentsApi.attachGuardian(student.id, payload),
    onSuccess: () => {
      toast.success(t('students.guardianLinkedToast'))
      invalidate()
      setModalOpen(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const detachMutation = useMutation({
    mutationFn: (guardianId: number) => studentsApi.detachGuardian(student.id, guardianId),
    onSuccess: () => {
      toast.success(t('students.guardianUnlinkedToast'))
      invalidate()
    },
  })

  const inviteMutation = useMutation({
    mutationFn: (guardianId: number) => guardiansApi.invite(guardianId),
    onSuccess: () => toast.success(t('students.portalInviteSentToast')),
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <div className="flex flex-col gap-4">
      {can('students.edit') && (
        <div>
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setForm(emptyForm)
              setModalOpen(true)
            }}
          >
            <Plus className="h-3.5 w-3.5" /> {t('students.linkGuardianAction')}
          </Button>
        </div>
      )}

      {(student.guardians?.length ?? 0) === 0 && <EmptyState title={t('students.noGuardiansLinkedYet')} />}

      {(student.guardians?.length ?? 0) > 0 && (
        <ul className="divide-y divide-border rounded-lg border border-border">
          {student.guardians?.map((guardian) => (
            <li key={guardian.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="font-medium">
                  {guardian.full_name}{' '}
                  {guardian.is_primary && (
                    <Badge variant="primary" className="ms-1">
                      {t('students.primaryBadge')}
                    </Badge>
                  )}
                </p>
                <p className="text-sm text-muted-foreground">
                  {t(GUARDIAN_RELATIONSHIP_LABEL_KEYS[guardian.relationship_type])} · {guardian.phone}
                  {guardian.email ? ` · ${guardian.email}` : ''}
                </p>
              </div>
              <div className="flex gap-2">
                {can('guardians.edit') && (
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => inviteMutation.mutate(guardian.id)}
                    isLoading={inviteMutation.isPending && inviteMutation.variables === guardian.id}
                  >
                    <Send className="h-3.5 w-3.5" /> {t('students.inviteAction')}
                  </Button>
                )}
                {can('students.edit') && (
                  <Button variant="outline" size="sm" onClick={() => detachMutation.mutate(guardian.id)} aria-label={t('students.unlinkGuardianAria', { name: guardian.full_name })}>
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={t('students.linkGuardianModalTitle')} size="sm">
        <form
          onSubmit={(e) => {
            e.preventDefault()
            attachMutation.mutate(form)
          }}
          className="flex flex-col gap-4"
          noValidate
        >
          <div className="grid grid-cols-2 gap-3">
            <FormField label={t('auth.firstName')} required>
              <Input required value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} />
            </FormField>
            <FormField label={t('auth.lastName')} required>
              <Input required value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} />
            </FormField>
          </div>
          <FormField label={t('common.phone')} required>
            <Input required value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          </FormField>
          <FormField label={t('auth.email')}>
            <Input type="email" value={form.email ?? ''} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          </FormField>
          <FormField label={t('students.relationshipLabel')} required>
            <Select
              value={form.relationship_type}
              onValueChange={(value) => setForm({ ...form, relationship_type: value as StudentGuardianInput['relationship_type'] })}
              options={GUARDIAN_RELATIONSHIPS.map((r) => ({ value: r, label: t(GUARDIAN_RELATIONSHIP_LABEL_KEYS[r]) }))}
            />
          </FormField>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox checked={!!form.is_primary} onCheckedChange={(checked) => setForm({ ...form, is_primary: checked })} />
            {t('students.primaryGuardianCheckboxLabel')}
          </label>
          <Button type="submit" isLoading={attachMutation.isPending} className="mt-2">
            {t('students.linkGuardianSubmit')}
          </Button>
        </form>
      </Modal>
    </div>
  )
}
