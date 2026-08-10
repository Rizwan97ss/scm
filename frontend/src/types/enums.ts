/**
 * Mirrors the backend's App\Enums\* classes. These are protocol-level values
 * (the API request/response contract), not admin-configurable data, so they
 * live here as the single frontend source of truth rather than being
 * hardcoded inline wherever a status badge or select option is rendered.
 */

export const GENDERS = ['male', 'female', 'other'] as const
export type Gender = (typeof GENDERS)[number]
export const GENDER_LABELS: Record<Gender, string> = {
  male: 'Male',
  female: 'Female',
  other: 'Other',
}

export const USER_STATUSES = ['active', 'inactive', 'suspended'] as const
export type UserStatus = (typeof USER_STATUSES)[number]
export const USER_STATUS_LABELS: Record<UserStatus, string> = {
  active: 'Active',
  inactive: 'Inactive',
  suspended: 'Suspended',
}

export const STUDENT_STATUSES = ['active', 'transferred_out', 'withdrawn', 'graduated', 'alumni'] as const
export type StudentStatus = (typeof STUDENT_STATUSES)[number]
export const STUDENT_STATUS_LABELS: Record<StudentStatus, string> = {
  active: 'Active',
  transferred_out: 'Transferred Out',
  withdrawn: 'Withdrawn',
  graduated: 'Graduated',
  alumni: 'Alumni',
}

export const ENROLLMENT_ACTIONS = [
  'admission',
  'promotion',
  'transfer_in',
  'transfer_out',
  'withdrawal',
  'graduation',
  'reactivation',
] as const
export type EnrollmentAction = (typeof ENROLLMENT_ACTIONS)[number]
export const ENROLLMENT_ACTION_LABELS: Record<EnrollmentAction, string> = {
  admission: 'Admission',
  promotion: 'Promotion',
  transfer_in: 'Transfer In',
  transfer_out: 'Transfer Out',
  withdrawal: 'Withdrawal',
  graduation: 'Graduation',
  reactivation: 'Reactivation',
}

export const GUARDIAN_RELATIONSHIPS = ['father', 'mother', 'guardian', 'other'] as const
export type GuardianRelationship = (typeof GUARDIAN_RELATIONSHIPS)[number]
export const GUARDIAN_RELATIONSHIP_LABELS: Record<GuardianRelationship, string> = {
  father: 'Father',
  mother: 'Mother',
  guardian: 'Guardian',
  other: 'Other',
}

export const ROOM_TYPES = ['classroom', 'lab', 'hall', 'other'] as const
export type RoomType = (typeof ROOM_TYPES)[number]
export const ROOM_TYPE_LABELS: Record<RoomType, string> = {
  classroom: 'Classroom',
  lab: 'Lab',
  hall: 'Hall',
  other: 'Other',
}

export const HOLIDAY_TYPES = ['public', 'school_specific'] as const
export type HolidayType = (typeof HOLIDAY_TYPES)[number]
export const HOLIDAY_TYPE_LABELS: Record<HolidayType, string> = {
  public: 'Public Holiday',
  school_specific: 'School-Specific',
}

export const ACADEMIC_YEAR_STATUSES = ['upcoming', 'active', 'closed'] as const
export type AcademicYearStatus = (typeof ACADEMIC_YEAR_STATUSES)[number]

export const ATTENDANCE_STATUSES = ['present', 'absent', 'late', 'half_day', 'excused', 'on_leave'] as const
export type AttendanceStatus = (typeof ATTENDANCE_STATUSES)[number]
export const ATTENDANCE_STATUS_LABELS: Record<AttendanceStatus, string> = {
  present: 'Present',
  absent: 'Absent',
  late: 'Late',
  half_day: 'Half Day',
  excused: 'Excused',
  on_leave: 'On Leave',
}

/** Carbon/PHP day-of-week numbering: 0 = Sunday ... 6 = Saturday. */
export const DAYS_OF_WEEK: { value: number; label: string }[] = [
  { value: 0, label: 'Sunday' },
  { value: 1, label: 'Monday' },
  { value: 2, label: 'Tuesday' },
  { value: 3, label: 'Wednesday' },
  { value: 4, label: 'Thursday' },
  { value: 5, label: 'Friday' },
  { value: 6, label: 'Saturday' },
]
