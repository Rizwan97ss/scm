import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { studentsApi } from '@/api/endpoints/students'
import { academicYearsApi, gradeLevelsApi, sectionsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { Button, FormField, Modal, Select, Textarea } from '@/components/ui'
import type { Student } from '@/types/student'
import type { ApiError } from '@/api/client'

export type EnrollmentActionType = 'promote' | 'transfer' | 'withdraw' | 'graduate' | 'reactivate'

const ACTION_COPY: Record<EnrollmentActionType, { title: string; needsTarget: boolean; confirmLabel: string }> = {
  promote: { title: 'Promote Student', needsTarget: true, confirmLabel: 'Promote' },
  transfer: { title: 'Transfer Student Out', needsTarget: false, confirmLabel: 'Confirm Transfer' },
  withdraw: { title: 'Withdraw Student', needsTarget: false, confirmLabel: 'Confirm Withdrawal' },
  graduate: { title: 'Graduate Student', needsTarget: false, confirmLabel: 'Confirm Graduation' },
  reactivate: { title: 'Reactivate Student', needsTarget: true, confirmLabel: 'Reactivate' },
}

export function StudentEnrollmentActionModal({
  student,
  action,
  onOpenChange,
}: {
  student: Student
  action: EnrollmentActionType | null
  onOpenChange: (open: boolean) => void
}) {
  const queryClient = useQueryClient()
  const [reason, setReason] = useState('')
  const [gradeLevelId, setGradeLevelId] = useState<number>()
  const [sectionId, setSectionId] = useState<number>()
  const [academicYearId, setAcademicYearId] = useState<number>()

  const { data: gradeLevels } = useQuery({ queryKey: queryKeys.gradeLevels({ per_page: 100 }), queryFn: () => gradeLevelsApi.list({ per_page: 100 }) })
  const { data: academicYears } = useQuery({ queryKey: queryKeys.academicYears({ per_page: 100 }), queryFn: () => academicYearsApi.list({ per_page: 100 }) })
  const { data: sections } = useQuery({
    queryKey: queryKeys.sections({ grade_level_id: gradeLevelId }),
    queryFn: () => sectionsApi.list({ per_page: 100, 'filter[grade_level_id]': gradeLevelId }),
    enabled: !!gradeLevelId,
  })

  const mutation = useMutation({
    mutationFn: async () => {
      if (!action) return
      if (action === 'promote') {
        if (!gradeLevelId || !sectionId || !academicYearId) throw new Error('Select grade level, section, and academic year.')
        return studentsApi.promote(student.id, { to_grade_level_id: gradeLevelId, to_section_id: sectionId, to_academic_year_id: academicYearId, reason })
      }
      if (action === 'reactivate') {
        if (!gradeLevelId || !sectionId) throw new Error('Select grade level and section.')
        return studentsApi.reactivate(student.id, { to_grade_level_id: gradeLevelId, to_section_id: sectionId, reason })
      }
      if (action === 'transfer') return studentsApi.transfer(student.id, { reason })
      if (action === 'withdraw') return studentsApi.withdraw(student.id, { reason })
      return studentsApi.graduate(student.id, { reason })
    },
    onSuccess: () => {
      toast.success('Student record updated.')
      queryClient.invalidateQueries({ queryKey: queryKeys.student(student.id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.studentEnrollmentHistory(student.id) })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as Error | ApiError).message),
  })

  if (!action) return null
  const copy = ACTION_COPY[action]

  return (
    <Modal open={!!action} onOpenChange={onOpenChange} title={copy.title} size="sm">
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        {copy.needsTarget && (
          <>
            <FormField label="Grade level" required>
              <Select
                value={gradeLevelId ? String(gradeLevelId) : undefined}
                onValueChange={(value) => setGradeLevelId(Number(value))}
                options={(gradeLevels?.data ?? []).map((level) => ({ value: String(level.id), label: level.name }))}
              />
            </FormField>
            <FormField label="Section" required>
              <Select
                value={sectionId ? String(sectionId) : undefined}
                onValueChange={(value) => setSectionId(Number(value))}
                options={(sections?.data ?? []).map((section) => ({ value: String(section.id), label: section.name }))}
                disabled={!gradeLevelId}
              />
            </FormField>
            {action === 'promote' && (
              <FormField label="Academic year" required>
                <Select
                  value={academicYearId ? String(academicYearId) : undefined}
                  onValueChange={(value) => setAcademicYearId(Number(value))}
                  options={(academicYears?.data ?? []).map((year) => ({ value: String(year.id), label: year.name }))}
                />
              </FormField>
            )}
          </>
        )}
        <FormField label="Reason" hint="Optional, recorded in the enrollment history">
          <Textarea value={reason} onChange={(e) => setReason(e.target.value)} />
        </FormField>
        <Button type="submit" isLoading={mutation.isPending} className="mt-2">
          {copy.confirmLabel}
        </Button>
      </form>
    </Modal>
  )
}
