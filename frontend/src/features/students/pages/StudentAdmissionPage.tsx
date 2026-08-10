import { useForm, useFieldArray } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'
import { studentAdmissionSchema, type StudentAdmissionFormValues } from '../schemas/studentAdmissionSchema'
import { studentsApi } from '@/api/endpoints/students'
import { academicYearsApi, gradeLevelsApi, sectionsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button, DatePicker, FormField, Input, Select } from '@/components/ui'
import { GENDERS, GENDER_LABELS, GUARDIAN_RELATIONSHIPS, GUARDIAN_RELATIONSHIP_LABELS } from '@/types/enums'
import { routePaths } from '@/routes/routePaths'
import type { ApiError } from '@/api/client'

export function StudentAdmissionPage() {
  const navigate = useNavigate()

  const { data: academicYears } = useQuery({ queryKey: queryKeys.academicYears({ per_page: 100 }), queryFn: () => academicYearsApi.list({ per_page: 100 }) })
  const { data: gradeLevels } = useQuery({ queryKey: queryKeys.gradeLevels({ per_page: 100 }), queryFn: () => gradeLevelsApi.list({ per_page: 100 }) })

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    control,
    formState: { errors, isSubmitting },
  } = useForm<StudentAdmissionFormValues>({
    resolver: zodResolver(studentAdmissionSchema),
    defaultValues: {
      gender: 'male',
      admission_date: new Date().toISOString().slice(0, 10),
      guardians: [],
    },
  })

  const { fields, append, remove } = useFieldArray({ control, name: 'guardians' })

  const gradeLevelId = watch('current_grade_level_id')
  const { data: sections } = useQuery({
    queryKey: queryKeys.sections({ grade_level_id: gradeLevelId }),
    queryFn: () => sectionsApi.list({ per_page: 100, 'filter[grade_level_id]': gradeLevelId }),
    enabled: !!gradeLevelId,
  })

  const createMutation = useMutation({
    mutationFn: studentsApi.create,
    onSuccess: (student) => {
      toast.success(`${student.full_name} admitted successfully.`)
      navigate(routePaths.studentProfile(student.id))
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  function onSubmit(values: StudentAdmissionFormValues) {
    createMutation.mutate({
      ...values,
      guardians: values.guardians.map((guardian) => ({ ...guardian, email: guardian.email || null })),
    })
  }

  return (
    <div>
      <PageHeader title="New Student Admission" breadcrumbs={[{ label: 'Students', to: routePaths.students }, { label: 'Admission' }]} />

      <form onSubmit={handleSubmit(onSubmit)} className="flex max-w-3xl flex-col gap-8" noValidate>
        <section className="flex flex-col gap-4">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Student Details</h2>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label="First name" htmlFor="first_name" error={errors.first_name?.message} required>
              <Input id="first_name" invalid={!!errors.first_name} {...register('first_name')} />
            </FormField>
            <FormField label="Last name" htmlFor="last_name" error={errors.last_name?.message} required>
              <Input id="last_name" invalid={!!errors.last_name} {...register('last_name')} />
            </FormField>
            <FormField label="Gender" htmlFor="gender" required>
              <Select
                id="gender"
                value={watch('gender')}
                onValueChange={(value) => setValue('gender', value as StudentAdmissionFormValues['gender'])}
                options={GENDERS.map((g) => ({ value: g, label: GENDER_LABELS[g] }))}
              />
            </FormField>
            <FormField label="Date of birth" htmlFor="date_of_birth" error={errors.date_of_birth?.message} required>
              <DatePicker id="date_of_birth" invalid={!!errors.date_of_birth} {...register('date_of_birth')} />
            </FormField>
            <FormField label="Blood group" htmlFor="blood_group">
              <Input id="blood_group" {...register('blood_group')} />
            </FormField>
            <FormField label="Nationality" htmlFor="nationality">
              <Input id="nationality" {...register('nationality')} />
            </FormField>
          </div>
        </section>

        <section className="flex flex-col gap-4">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Enrollment</h2>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label="Academic year" htmlFor="academic_year_id" error={errors.academic_year_id?.message} required>
              <Select
                id="academic_year_id"
                value={watch('academic_year_id') ? String(watch('academic_year_id')) : undefined}
                onValueChange={(value) => setValue('academic_year_id', Number(value))}
                options={(academicYears?.data ?? []).map((year) => ({ value: String(year.id), label: year.name }))}
                placeholder="Select academic year"
              />
            </FormField>
            <FormField label="Admission date" htmlFor="admission_date" error={errors.admission_date?.message} required>
              <DatePicker id="admission_date" invalid={!!errors.admission_date} {...register('admission_date')} />
            </FormField>
            <FormField label="Grade level" htmlFor="current_grade_level_id">
              <Select
                id="current_grade_level_id"
                value={gradeLevelId ? String(gradeLevelId) : undefined}
                onValueChange={(value) => {
                  setValue('current_grade_level_id', Number(value))
                  setValue('current_section_id', undefined)
                }}
                options={(gradeLevels?.data ?? []).map((level) => ({ value: String(level.id), label: level.name }))}
                placeholder="Select grade level"
              />
            </FormField>
            <FormField label="Section" htmlFor="current_section_id">
              <Select
                id="current_section_id"
                value={watch('current_section_id') ? String(watch('current_section_id')) : undefined}
                onValueChange={(value) => setValue('current_section_id', Number(value))}
                options={(sections?.data ?? []).map((section) => ({ value: String(section.id), label: section.name }))}
                placeholder={gradeLevelId ? 'Select section' : 'Select a grade level first'}
                disabled={!gradeLevelId}
              />
            </FormField>
            <FormField label="Roll number" htmlFor="roll_number">
              <Input id="roll_number" {...register('roll_number')} />
            </FormField>
            <FormField label="Previous school" htmlFor="previous_school_name">
              <Input id="previous_school_name" {...register('previous_school_name')} />
            </FormField>
          </div>
        </section>

        <section className="flex flex-col gap-4">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Emergency Contact</h2>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FormField label="Contact name" htmlFor="emergency_contact_name">
              <Input id="emergency_contact_name" {...register('emergency_contact_name')} />
            </FormField>
            <FormField label="Contact phone" htmlFor="emergency_contact_phone">
              <Input id="emergency_contact_phone" {...register('emergency_contact_phone')} />
            </FormField>
          </div>
        </section>

        <section className="flex flex-col gap-4">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Guardians</h2>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => append({ first_name: '', last_name: '', phone: '', relationship_type: 'guardian', is_primary: fields.length === 0 })}
            >
              <Plus className="h-3.5 w-3.5" /> Add guardian
            </Button>
          </div>

          {fields.length === 0 && <p className="text-sm text-muted-foreground">No guardians added yet. You can also link one later from the student's profile.</p>}

          {fields.map((field, index) => (
            <div key={field.id} className="flex flex-col gap-3 rounded-md border border-border p-4">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">Guardian {index + 1}</span>
                <Button type="button" variant="ghost" size="sm" onClick={() => remove(index)} aria-label="Remove guardian">
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormField label="First name" required>
                  <Input {...register(`guardians.${index}.first_name`)} />
                </FormField>
                <FormField label="Last name" required>
                  <Input {...register(`guardians.${index}.last_name`)} />
                </FormField>
                <FormField label="Phone" required>
                  <Input {...register(`guardians.${index}.phone`)} />
                </FormField>
                <FormField label="Email">
                  <Input type="email" {...register(`guardians.${index}.email`)} />
                </FormField>
                <FormField label="Relationship" required>
                  <Select
                    value={watch(`guardians.${index}.relationship_type`)}
                    onValueChange={(value) => setValue(`guardians.${index}.relationship_type`, value as (typeof GUARDIAN_RELATIONSHIPS)[number])}
                    options={GUARDIAN_RELATIONSHIPS.map((r) => ({ value: r, label: GUARDIAN_RELATIONSHIP_LABELS[r] }))}
                  />
                </FormField>
              </div>
            </div>
          ))}
        </section>

        <div>
          <Button type="submit" isLoading={isSubmitting || createMutation.isPending}>
            Admit student
          </Button>
        </div>
      </form>
    </div>
  )
}
