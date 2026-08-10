import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { academicYearsApi, classSubjectTeachersApi, sectionsApi, subjectsApi } from '@/api/endpoints/academics'
import { usersApi } from '@/api/endpoints/users'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { Button, EmptyState, FormField, Modal, Select, Skeleton } from '@/components/ui'
import type { ClassSubjectTeacherPayload } from '@/types/academic'
import type { ApiError } from '@/api/client'

export function ClassSubjectTeacherManager() {
  const { can } = usePermission()
  const queryClient = useQueryClient()

  const { data: academicYears } = useQuery({ queryKey: queryKeys.academicYears({ per_page: 100 }), queryFn: () => academicYearsApi.list({ per_page: 100 }) })
  const currentYearId = academicYears?.data.find((y) => y.is_current)?.id ?? academicYears?.data[0]?.id

  const { data: sections } = useQuery({
    queryKey: queryKeys.sections({ academic_year_id: currentYearId }),
    queryFn: () => sectionsApi.list({ per_page: 100, 'filter[academic_year_id]': currentYearId }),
    enabled: !!currentYearId,
  })
  const [sectionId, setSectionId] = useState<number | undefined>()
  const activeSectionId = sectionId ?? sections?.data[0]?.id

  const { data: assignments, isLoading } = useQuery({
    queryKey: queryKeys.classSubjectTeachers({ section_id: activeSectionId, academic_year_id: currentYearId }),
    queryFn: () => classSubjectTeachersApi.list({ section_id: activeSectionId, academic_year_id: currentYearId }),
    enabled: !!activeSectionId,
  })
  const { data: subjects } = useQuery({ queryKey: queryKeys.subjects({ per_page: 100 }), queryFn: () => subjectsApi.list({ per_page: 100 }) })
  const { data: teachers } = useQuery({
    queryKey: queryKeys.users({ role: 'Teacher' }),
    queryFn: () => usersApi.list({ per_page: 100, 'filter[role]': 'Teacher' }),
  })

  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<{ subject_id?: number; teacher_id?: number }>({})

  const invalidate = () => queryClient.invalidateQueries({ queryKey: queryKeys.classSubjectTeachers() })

  const createMutation = useMutation({
    mutationFn: (payload: ClassSubjectTeacherPayload) => classSubjectTeachersApi.create(payload),
    onSuccess: () => {
      toast.success('Teacher assigned to subject.')
      invalidate()
      setModalOpen(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const removeMutation = useMutation({
    mutationFn: classSubjectTeachersApi.remove,
    onSuccess: () => {
      toast.success('Assignment removed.')
      invalidate()
    },
  })

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (!activeSectionId || !currentYearId || !form.subject_id || !form.teacher_id) return
    await createMutation.mutateAsync({
      academic_year_id: currentYearId,
      section_id: activeSectionId,
      subject_id: form.subject_id,
      teacher_id: form.teacher_id,
    })
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <FormField label="Section" className="w-56">
          <Select
            value={activeSectionId ? String(activeSectionId) : undefined}
            onValueChange={(value) => setSectionId(Number(value))}
            options={(sections?.data ?? []).map((section) => ({ value: String(section.id), label: `${section.grade_level?.name} - ${section.name}` }))}
            placeholder="Select a section"
          />
        </FormField>
        {can('timetable.create') && (
          <Button
            onClick={() => {
              setForm({})
              setModalOpen(true)
            }}
            disabled={!activeSectionId}
          >
            <Plus className="h-4 w-4" /> Assign Teacher
          </Button>
        )}
      </div>

      {isLoading && <Skeleton className="h-40 w-full" />}

      {!isLoading && activeSectionId && (assignments?.length ?? 0) === 0 && (
        <EmptyState title="No subject-teacher assignments yet" description="Assign teachers to subjects for this section." />
      )}

      {!isLoading && (assignments?.length ?? 0) > 0 && (
        <ul className="divide-y divide-border rounded-lg border border-border">
          {assignments?.map((assignment) => (
            <li key={assignment.id} className="flex items-center justify-between gap-4 px-4 py-3">
              <div>
                <p className="font-medium">{assignment.subject.name}</p>
                <p className="text-sm text-muted-foreground">{assignment.teacher.full_name}</p>
              </div>
              {can('timetable.delete') && (
                <Button variant="outline" size="sm" onClick={() => removeMutation.mutate(assignment.id)} aria-label="Remove assignment">
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}

      <Modal open={modalOpen} onOpenChange={setModalOpen} title="Assign Teacher to Subject" size="sm">
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Subject" htmlFor="subject_id" required>
            <Select
              id="subject_id"
              value={form.subject_id ? String(form.subject_id) : undefined}
              onValueChange={(value) => setForm({ ...form, subject_id: Number(value) })}
              options={(subjects?.data ?? []).map((subject) => ({ value: String(subject.id), label: subject.name }))}
            />
          </FormField>
          <FormField label="Teacher" htmlFor="teacher_id" required>
            <Select
              id="teacher_id"
              value={form.teacher_id ? String(form.teacher_id) : undefined}
              onValueChange={(value) => setForm({ ...form, teacher_id: Number(value) })}
              options={(teachers?.data ?? []).map((teacher) => ({ value: String(teacher.id), label: teacher.full_name }))}
            />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending} className="mt-2">
            Assign
          </Button>
        </form>
      </Modal>
    </div>
  )
}
