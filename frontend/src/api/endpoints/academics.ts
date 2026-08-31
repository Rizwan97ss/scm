import { httpClient } from '@/api/client'
import { createCrudEndpoints, createImportEndpoints } from './crudFactory'
import type { ApiResponse } from '@/types/api'
import type {
  AcademicYear,
  AcademicYearPayload,
  ClassSubjectTeacher,
  ClassSubjectTeacherPayload,
  Department,
  DepartmentPayload,
  GradeLevel,
  GradeLevelPayload,
  Holiday,
  HolidayPayload,
  Room,
  RoomPayload,
  Section,
  SectionPayload,
  Subject,
  SubjectPayload,
  Term,
  TermPayload,
  TimetableEntry,
  TimetableEntryPayload,
  TimetablePeriod,
  TimetablePeriodPayload,
} from '@/types/academic'

export const academicYearsApi = {
  ...createCrudEndpoints<AcademicYear, AcademicYearPayload>('academic-years'),
  activate: async (id: number): Promise<AcademicYear> => {
    const { data } = await httpClient.post<ApiResponse<AcademicYear>>(`/academic-years/${id}/activate`)
    return data.data
  },
}

export const termsApi = createCrudEndpoints<Term, TermPayload>('terms')
export const departmentsApi = { ...createCrudEndpoints<Department, DepartmentPayload>('departments'), ...createImportEndpoints('departments') }
export const gradeLevelsApi = { ...createCrudEndpoints<GradeLevel, GradeLevelPayload>('grade-levels'), ...createImportEndpoints('grade-levels') }
export const sectionsApi = { ...createCrudEndpoints<Section, SectionPayload>('sections'), ...createImportEndpoints('sections') }
export const subjectsApi = { ...createCrudEndpoints<Subject, SubjectPayload>('subjects'), ...createImportEndpoints('subjects') }
export const roomsApi = { ...createCrudEndpoints<Room, RoomPayload>('rooms'), ...createImportEndpoints('rooms') }
export const holidaysApi = createCrudEndpoints<Holiday, HolidayPayload>('holidays')
export const timetablePeriodsApi = createCrudEndpoints<TimetablePeriod, TimetablePeriodPayload>('timetable-periods')

export const classSubjectTeachersApi = {
  list: async (params?: { section_id?: number; academic_year_id?: number }): Promise<ClassSubjectTeacher[]> => {
    const { data } = await httpClient.get<ApiResponse<ClassSubjectTeacher[]>>('/class-subject-teachers', { params })
    return data.data
  },
  create: async (payload: ClassSubjectTeacherPayload): Promise<ClassSubjectTeacher> => {
    const { data } = await httpClient.post<ApiResponse<ClassSubjectTeacher>>('/class-subject-teachers', payload)
    return data.data
  },
  remove: async (id: number): Promise<void> => {
    await httpClient.delete(`/class-subject-teachers/${id}`)
  },
}

export const timetableApi = {
  grid: async (params: { section_id?: number; teacher_id?: number; academic_year_id?: number }): Promise<TimetableEntry[]> => {
    const { data } = await httpClient.get<ApiResponse<TimetableEntry[]>>('/timetable', { params })
    return data.data
  },
  createEntry: async (payload: TimetableEntryPayload): Promise<TimetableEntry> => {
    const { data } = await httpClient.post<ApiResponse<TimetableEntry>>('/timetable-entries', payload)
    return data.data
  },
  updateEntry: async (id: number, payload: Partial<TimetableEntryPayload>): Promise<TimetableEntry> => {
    const { data } = await httpClient.put<ApiResponse<TimetableEntry>>(`/timetable-entries/${id}`, payload)
    return data.data
  },
  removeEntry: async (id: number): Promise<void> => {
    await httpClient.delete(`/timetable-entries/${id}`)
  },
}
