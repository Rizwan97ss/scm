import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, X } from 'lucide-react'
import { toast } from 'sonner'
import { academicYearsApi, classSubjectTeachersApi, roomsApi, sectionsApi, timetableApi, timetablePeriodsApi } from '@/api/endpoints/academics'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { Button, EmptyState, FormField, Modal, Select } from '@/components/ui'
import { DAYS_OF_WEEK } from '@/types/enums'
import type { TimetableEntryPayload } from '@/types/academic'
import type { ApiError } from '@/api/client'

export function TimetableGridBuilder() {
  const { can } = usePermission()
  const queryClient = useQueryClient()

  const { data: academicYears } = useQuery({ queryKey: queryKeys.academicYears({ per_page: 100 }), queryFn: () => academicYearsApi.list({ per_page: 100 }) })
  const [academicYearId, setAcademicYearId] = useState<number | undefined>()
  const currentYearId = academicYearId ?? academicYears?.data.find((y) => y.is_current)?.id ?? academicYears?.data[0]?.id

  const { data: sections } = useQuery({
    queryKey: queryKeys.sections({ academic_year_id: currentYearId }),
    queryFn: () => sectionsApi.list({ per_page: 100, 'filter[academic_year_id]': currentYearId }),
    enabled: !!currentYearId,
  })
  const [sectionId, setSectionId] = useState<number | undefined>()
  const activeSectionId = sectionId ?? sections?.data[0]?.id

  const { data: periods } = useQuery({ queryKey: queryKeys.timetablePeriods({ per_page: 50 }), queryFn: () => timetablePeriodsApi.list({ per_page: 50, sort: 'sequence' }) })
  const { data: entries } = useQuery({
    queryKey: queryKeys.timetable({ section_id: activeSectionId }),
    queryFn: () => timetableApi.grid({ section_id: activeSectionId }),
    enabled: !!activeSectionId,
  })
  const { data: assignments } = useQuery({
    queryKey: queryKeys.classSubjectTeachers({ section_id: activeSectionId, academic_year_id: currentYearId }),
    queryFn: () => classSubjectTeachersApi.list({ section_id: activeSectionId, academic_year_id: currentYearId }),
    enabled: !!activeSectionId,
  })
  const { data: rooms } = useQuery({ queryKey: queryKeys.rooms({ per_page: 100 }), queryFn: () => roomsApi.list({ per_page: 100 }) })

  const [cell, setCell] = useState<{ periodId: number; day: number } | null>(null)
  const [entryForm, setEntryForm] = useState<{ subject_id?: number; teacher_id?: number; room_id?: number }>({})

  const createEntry = useMutation({
    mutationFn: (payload: TimetableEntryPayload) => timetableApi.createEntry(payload),
    onSuccess: () => {
      toast.success('Timetable entry added.')
      queryClient.invalidateQueries({ queryKey: queryKeys.timetable({ section_id: activeSectionId }) })
      setCell(null)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const removeEntry = useMutation({
    mutationFn: timetableApi.removeEntry,
    onSuccess: () => {
      toast.success('Entry removed.')
      queryClient.invalidateQueries({ queryKey: queryKeys.timetable({ section_id: activeSectionId }) })
    },
  })

  function entryFor(periodId: number, day: number) {
    return entries?.find((entry) => entry.timetable_period_id === periodId && entry.day_of_week === day)
  }

  async function submitEntry(event: React.FormEvent) {
    event.preventDefault()
    if (!cell || !activeSectionId || !currentYearId) return
    await createEntry.mutateAsync({
      academic_year_id: currentYearId,
      section_id: activeSectionId,
      timetable_period_id: cell.periodId,
      day_of_week: cell.day,
      subject_id: entryForm.subject_id ?? null,
      teacher_id: entryForm.teacher_id ?? null,
      room_id: entryForm.room_id ?? null,
    })
  }

  const teachingPeriods = periods?.data.filter((p) => !p.is_break) ?? []

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap gap-4">
        <FormField label="Academic Year" className="w-56">
          <Select
            value={currentYearId ? String(currentYearId) : undefined}
            onValueChange={(value) => setAcademicYearId(Number(value))}
            options={(academicYears?.data ?? []).map((year) => ({ value: String(year.id), label: year.name }))}
          />
        </FormField>
        <FormField label="Section" className="w-56">
          <Select
            value={activeSectionId ? String(activeSectionId) : undefined}
            onValueChange={(value) => setSectionId(Number(value))}
            options={(sections?.data ?? []).map((section) => ({ value: String(section.id), label: `${section.grade_level?.name} - ${section.name}` }))}
            placeholder="Select a section"
          />
        </FormField>
      </div>

      {!activeSectionId && <EmptyState title="Select a section to view its timetable" />}

      {activeSectionId && teachingPeriods.length === 0 && (
        <EmptyState title="No periods configured" description="Add timetable periods in the Periods tab first." />
      )}

      {activeSectionId && teachingPeriods.length > 0 && (
        <div className="overflow-x-auto rounded-lg border border-border">
          <table className="w-full min-w-[900px] border-collapse text-sm">
            <thead>
              <tr className="bg-muted/50">
                <th className="whitespace-nowrap border-b border-border p-2 text-left text-xs font-medium uppercase text-muted-foreground">Period</th>
                {DAYS_OF_WEEK.filter((d) => d.value >= 1 && d.value <= 6).map((day) => (
                  <th key={day.value} className="whitespace-nowrap border-b border-border p-2 text-left text-xs font-medium uppercase text-muted-foreground">
                    {day.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {teachingPeriods.map((period) => (
                <tr key={period.id} className="border-b border-border last:border-0">
                  <td className="whitespace-nowrap p-2 align-top font-medium">
                    {period.name}
                    <div className="text-xs font-normal text-muted-foreground">
                      {period.start_time}–{period.end_time}
                    </div>
                  </td>
                  {DAYS_OF_WEEK.filter((d) => d.value >= 1 && d.value <= 6).map((day) => {
                    const entry = entryFor(period.id, day.value)
                    return (
                      <td key={day.value} className="min-w-36 p-2 align-top">
                        {entry ? (
                          <div className="group relative rounded-md border border-border bg-muted/40 p-2 text-xs">
                            <p className="font-medium text-foreground">{entry.subject?.name ?? 'Unassigned'}</p>
                            <p className="text-muted-foreground">{entry.teacher?.full_name}</p>
                            {entry.room && <p className="text-muted-foreground">{entry.room.name}</p>}
                            {can('timetable.delete') && (
                              <button
                                type="button"
                                onClick={() => removeEntry.mutate(entry.id)}
                                className="absolute right-1 top-1 hidden rounded p-0.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive group-hover:block"
                                aria-label="Remove entry"
                              >
                                <X className="h-3 w-3" />
                              </button>
                            )}
                          </div>
                        ) : (
                          can('timetable.create') && (
                            <button
                              type="button"
                              onClick={() => {
                                setEntryForm({})
                                setCell({ periodId: period.id, day: day.value })
                              }}
                              className="flex h-full w-full items-center justify-center rounded-md border border-dashed border-border p-2 text-muted-foreground hover:border-primary hover:text-primary"
                              aria-label={`Add entry for ${period.name} on ${day.label}`}
                            >
                              <Plus className="h-4 w-4" />
                            </button>
                          )
                        )}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={!!cell} onOpenChange={(open) => !open && setCell(null)} title="Add Timetable Entry" size="sm">
        <form onSubmit={submitEntry} className="flex flex-col gap-4" noValidate>
          <FormField label="Subject & Teacher" htmlFor="assignment" hint="From this section's assigned subject-teacher pairs">
            <Select
              id="assignment"
              value={entryForm.subject_id ? String(entryForm.subject_id) : undefined}
              onValueChange={(value) => {
                const assignment = assignments?.find((a) => a.subject.id === Number(value))
                setEntryForm({ ...entryForm, subject_id: Number(value), teacher_id: assignment?.teacher.id })
              }}
              options={(assignments ?? []).map((a) => ({ value: String(a.subject.id), label: `${a.subject.name} — ${a.teacher.full_name}` }))}
              placeholder="Select subject"
            />
          </FormField>
          <FormField label="Room" htmlFor="room" hint="Optional">
            <Select
              id="room"
              value={entryForm.room_id ? String(entryForm.room_id) : undefined}
              onValueChange={(value) => setEntryForm({ ...entryForm, room_id: Number(value) })}
              options={(rooms?.data ?? []).map((room) => ({ value: String(room.id), label: room.name }))}
              placeholder="No room"
            />
          </FormField>
          <Button type="submit" isLoading={createEntry.isPending} className="mt-2">
            Add to timetable
          </Button>
        </form>
      </Modal>
    </div>
  )
}
