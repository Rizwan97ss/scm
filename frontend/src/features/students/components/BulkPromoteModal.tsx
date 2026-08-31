import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { studentsApi } from '@/api/endpoints/students'
import { academicYearsApi, gradeLevelsApi, sectionsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { Button, FormField, Modal, Select, Textarea } from '@/components/ui'
import { formatApiError, type ApiError } from '@/api/client'

/** Same field set/cascading-select pattern as StudentEnrollmentActionModal's single-student promote, applied to a whole selection at once via the existing bulkPromote endpoint. */
export function BulkPromoteModal({ studentIds, open, onOpenChange }: { studentIds: number[]; open: boolean; onOpenChange: (open: boolean) => void }) {
  const { t } = useTranslation()
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
    mutationFn: () => {
      if (!gradeLevelId || !sectionId || !academicYearId) throw new Error(t('students.selectGradeSectionYearError'))
      return studentsApi.bulkPromote({ student_ids: studentIds, to_grade_level_id: gradeLevelId, to_section_id: sectionId, to_academic_year_id: academicYearId, reason })
    },
    onSuccess: (data) => {
      toast.success(t('students.bulkPromoteSuccessToast', { count: data.promoted_count }))
      // Partial key match — invalidates every students-list query regardless
      // of its filter/sort/page params, not just one exact params combination.
      queryClient.invalidateQueries({ queryKey: ['students'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(formatApiError(error as ApiError)),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={t('students.bulkPromoteTitle', { count: studentIds.length })} size="sm">
      <form
        onSubmit={(e) => {
          e.preventDefault()
          if (mutation.isPending) return
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label={t('entities.gradeLevel')} required>
          <Select
            value={gradeLevelId ? String(gradeLevelId) : undefined}
            onValueChange={(value) => setGradeLevelId(Number(value))}
            options={(gradeLevels?.data ?? []).map((level) => ({ value: String(level.id), label: level.name }))}
          />
        </FormField>
        <FormField label={t('entities.section')} required>
          <Select
            value={sectionId ? String(sectionId) : undefined}
            onValueChange={(value) => setSectionId(Number(value))}
            options={(sections?.data ?? []).map((section) => ({ value: String(section.id), label: section.name }))}
            disabled={!gradeLevelId}
          />
        </FormField>
        <FormField label={t('entities.academicYear')} required>
          <Select
            value={academicYearId ? String(academicYearId) : undefined}
            onValueChange={(value) => setAcademicYearId(Number(value))}
            options={(academicYears?.data ?? []).map((year) => ({ value: String(year.id), label: year.name }))}
          />
        </FormField>
        <FormField label={t('common.reason')} hint={t('students.reasonHintOptional')}>
          <Textarea value={reason} onChange={(e) => setReason(e.target.value)} />
        </FormField>
        <Button type="submit" isLoading={mutation.isPending} className="mt-2">
          {t('students.bulkPromoteSubmit', { count: studentIds.length })}
        </Button>
      </form>
    </Modal>
  )
}
