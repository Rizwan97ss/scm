import type { AcademicYearStatus, HolidayType, RoomType } from './enums'

export interface AcademicYear {
  id: number
  name: string
  start_date: string
  end_date: string
  is_current: boolean
  status: AcademicYearStatus
  created_at: string
}

export interface AcademicYearPayload {
  name: string
  start_date: string
  end_date: string
  is_current?: boolean
  status?: AcademicYearStatus
}

export interface Term {
  id: number
  academic_year_id: number
  name: string
  start_date: string
  end_date: string
  sequence: number
  is_current: boolean
}

export interface TermPayload {
  academic_year_id: number
  name: string
  start_date: string
  end_date: string
  sequence?: number
  is_current?: boolean
}

export interface Department {
  id: number
  name: string
  code: string
  description: string | null
}

export interface DepartmentPayload {
  name: string
  code: string
  description?: string | null
}

export interface GradeLevel {
  id: number
  name: string
  code: string
  sequence: number
}

export interface GradeLevelPayload {
  name: string
  code: string
  sequence?: number
}

export interface Room {
  id: number
  name: string
  code: string
  capacity: number | null
  type: RoomType
}

export interface RoomPayload {
  name: string
  code: string
  capacity?: number | null
  type?: RoomType
}

export interface Subject {
  id: number
  name: string
  code: string
  is_elective: boolean
  department_id: number | null
  department?: { id: number; name: string } | null
}

export interface SubjectPayload {
  name: string
  code: string
  department_id?: number | null
  is_elective?: boolean
}

export interface Section {
  id: number
  academic_year_id: number
  grade_level_id: number
  grade_level?: { id: number; name: string }
  name: string
  capacity: number | null
  student_count?: number
  class_teacher_id: number | null
  class_teacher?: { id: number; full_name: string } | null
  room_id: number | null
  room?: { id: number; name: string } | null
}

export interface SectionPayload {
  academic_year_id: number
  grade_level_id: number
  name: string
  capacity?: number | null
  class_teacher_id?: number | null
  room_id?: number | null
}

export interface Holiday {
  id: number
  academic_year_id: number | null
  name: string
  start_date: string
  end_date: string
  type: HolidayType
  description: string | null
}

export interface HolidayPayload {
  academic_year_id?: number | null
  name: string
  start_date: string
  end_date: string
  type?: HolidayType
  description?: string | null
}

export interface TimetablePeriod {
  id: number
  name: string
  start_time: string
  end_time: string
  sequence: number
  is_break: boolean
}

export interface TimetablePeriodPayload {
  name: string
  start_time: string
  end_time: string
  sequence?: number
  is_break?: boolean
}

export interface TimetableEntry {
  id: number
  academic_year_id: number
  section_id: number
  subject: { id: number; name: string } | null
  teacher: { id: number; full_name: string } | null
  room: { id: number; name: string } | null
  timetable_period_id: number
  period: { id: number; name: string; start_time: string; end_time: string }
  day_of_week: number
}

export interface TimetableEntryPayload {
  academic_year_id: number
  section_id: number
  subject_id?: number | null
  teacher_id?: number | null
  room_id?: number | null
  timetable_period_id: number
  day_of_week: number
}

export interface ClassSubjectTeacher {
  id: number
  academic_year_id: number
  section_id: number
  subject: { id: number; name: string }
  teacher: { id: number; full_name: string }
}

export interface ClassSubjectTeacherPayload {
  academic_year_id: number
  section_id: number
  subject_id: number
  teacher_id: number
}
